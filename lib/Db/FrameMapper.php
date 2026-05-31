<?php

declare(strict_types=1);

namespace OCA\MediaFrames\Db;

use DateTime;
use Exception;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\IMimeTypeLoader;
use OCP\Files\IRootFolder;
use OCP\Files\Folder;
use OCP\FilesMetadata\IFilesMetadataManager;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Security\ISecureRandom;

class FrameMapper extends QBMapper
{
  public const SELECTION_METHOD_LATEST = 'latest';
  public const SELECTION_METHOD_OLDEST = 'oldest';
  public const SELECTION_METHOD_RANDOM = 'random';

  public const ROTATION_UNIT_HOUR = 'hour';
  public const ROTATION_UNIT_DAY = 'day';
  public const ROTATION_UNIT_MINUTE = 'minute';

  private const CHUNK_SIZE = 500;

  private ISecureRandom $random;
  private IDBConnection $connection;
  private IMimeTypeLoader $mimeTypeLoader;
  private IRootFolder $rootFolder;
  private IConfig $config;
  private IFilesMetadataManager $metadataManager;

  public function __construct(
    IDBConnection $db,
    ISecureRandom $random,
    IDBConnection $connection,
    IMimeTypeLoader $mimeTypeLoader,
    IConfig $config,
    IRootFolder $rootFolder,
    IFilesMetadataManager $metadataManager
  ) {
    parent::__construct($db, 'media_frames_frames', Frame::class);
    $this->random = $random;
    $this->connection = $connection;
    $this->mimeTypeLoader = $mimeTypeLoader;
    $this->config = $config;
    $this->rootFolder = $rootFolder;
    $this->metadataManager = $metadataManager;
  }

  public function getAllByUser(string $userId): array
  {
    $qb = $this->db->getQueryBuilder();

    $qb->select('*')
      ->from($this->getTableName())
      ->where(
        $qb->expr()->eq('user_uid', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
      )
      ->orderBy('created_at', 'desc');

    return $this->findEntities($qb);
  }

  public function getByUserIdAndFrameId(string $userId, int $frameId): Frame
  {
    $qb = $this->db->getQueryBuilder();

    $qb->select('*')
      ->from($this->getTableName())
      ->where(
        $qb->expr()->andX(
          $qb->expr()->eq('id', $qb->createNamedParameter($frameId, IQueryBuilder::PARAM_INT)),
          $qb->expr()->eq('user_uid', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
        )
      );

    return $this->findEntity($qb);
  }

  public function getByShareToken(string $shareToken): ?Frame
  {
    $qb = $this->db->getQueryBuilder();

    $qb->select('*')
      ->from($this->getTableName())
      ->where(
        $qb->expr()->eq('share_token', $qb->createNamedParameter($shareToken, IQueryBuilder::PARAM_STR))
      );

    try {
      $frame = $this->findEntity($qb);
    } catch (\OCP\AppFramework\Db\DoesNotExistException) {
      return null;
    }

    $timezone = date_default_timezone_get();
    $timezone = $this->config->getUserValue($frame->getUserUid(), 'core', 'timezone', $timezone);
    $frame->setTimezone(new \DateTimeZone($timezone));

    return $frame;
  }

  /** @return Album[] */
  public function getAvailableAlbums(string $userId): array
  {
    return array_merge(
      $this->getAlbumsForUser($userId),
      $this->getSharedAlbumsForCollaborator($userId),
    );
  }

  /** @return Album[] */
  private function getAlbumsForUser(string $userId): array
  {
    if (!$this->connection->tableExists('photos_albums')) {
      return [];
    }

    try {
      $query = $this->connection->getQueryBuilder();
      $query->select('album_id', 'name')
        ->from('photos_albums')
        ->where($query->expr()->eq('user', $query->createNamedParameter($userId)));
      $rows = $query->executeQuery()->fetchAll();
    } catch (Exception) {
      return [];
    }

    return array_map(fn($row) => new Album((int) $row['album_id'], $row['name']), $rows);
  }

  /** @return Album[] */
  private function getSharedAlbumsForCollaborator(string $collaboratorId): array
  {
    if (!$this->connection->tableExists('photos_albums_collabs')) {
      return [];
    }

    try {
      $query = $this->connection->getQueryBuilder();
      $rows = $query
        ->select('a.album_id', 'a.name', 'a.user')
        ->from('photos_albums_collabs', 'c')
        ->leftJoin('c', 'photos_albums', 'a', $query->expr()->eq('a.album_id', 'c.album_id'))
        ->where($query->expr()->eq('collaborator_id', $query->createNamedParameter($collaboratorId)))
        ->andWhere($query->expr()->eq('collaborator_type', $query->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
        ->executeQuery()
        ->fetchAll();
    } catch (Exception) {
      return [];
    }

    return array_map(
      fn($row) => new Album((int) $row['album_id'], $row['name'] . ' (' . $row['user'] . ')'),
      $rows
    );
  }

  public function validAlbumForUser(string $userId, int $albumId): ?int
  {
    foreach ($this->getAvailableAlbums($userId) as $album) {
      if ($album->getId() === $albumId) {
        return $albumId;
      }
    }
    return null;
  }

  /** @return FrameFile[] */
  public function getFrameFiles(Frame $frame): array
  {
    $sources = $frame->getDecodedSources();
    $userId = $frame->getUserUid();
    $allFiles = [];
    $seenIds = [];

    foreach ($sources as $source) {
      $type = $source['type'] ?? '';

      if ($type === 'album') {
        $albumId = (int) ($source['albumId'] ?? 0);
        if ($albumId > 0) {
          foreach ($this->getFilesFromAlbum($albumId) as $file) {
            if (!in_array($file->getFileId(), $seenIds)) {
              $seenIds[] = $file->getFileId();
              $allFiles[] = $file;
            }
          }
        }
      } elseif ($type === 'folder') {
        $path = $source['path'] ?? '';
        $recursive = (bool) ($source['recursive'] ?? true);
        if ($path !== '') {
          foreach ($this->getFilesFromFolder($userId, $path, $recursive) as $file) {
            if (!in_array($file->getFileId(), $seenIds)) {
              $seenIds[] = $file->getFileId();
              $allFiles[] = $file;
            }
          }
        }
      }
    }

    return $allFiles;
  }

  /** @return FrameFile[] */
  private function getFilesFromAlbum(int $albumId): array
  {
    if (!$this->connection->tableExists('photos_albums_files')) {
      return [];
    }

    try {
      $query = $this->connection->getQueryBuilder();
      $query->select('album_files.file_id', 'added', 'owner', 'mtime', 'mimetype')
        ->from('photos_albums_files', 'album_files')
        ->innerJoin('album_files', 'filecache', 'file', $query->expr()->eq('album_files.file_id', 'file.fileid'))
        ->where($query->expr()->eq('album_files.album_id', $query->createNamedParameter($albumId, IQueryBuilder::PARAM_INT)));
      $rows = $query->executeQuery()->fetchAll();
    } catch (Exception) {
      return [];
    }

    $fileIds = array_column($rows, 'file_id');
    $metadatas = $this->getMetadataForFiles($fileIds);

    $files = [];
    foreach ($rows as $row) {
      $mimeType = $this->mimeTypeLoader->getMimetypeById((int) $row['mimetype']);
      if (!FrameFile::isSupportedMime($mimeType)) {
        continue;
      }
      $metadata = $metadatas[$row['file_id']] ?? [];
      $files[] = new FrameFile(
        (int) $row['file_id'],
        $row['owner'],
        $mimeType,
        (int) $row['added'],
        (int) ($metadata['capturedAt'] ?? $row['mtime'] ?? 0),
        $metadata['place'] ?? null,
      );
    }

    return $files;
  }

  /** @return FrameFile[] */
  private function getFilesFromFolder(string $userId, string $path, bool $recursive): array
  {
    try {
      $userFolder = $this->rootFolder->getUserFolder($userId);
      $node = $userFolder->get($path);
    } catch (Exception) {
      return [];
    }

    if (!($node instanceof Folder)) {
      return [];
    }

    $nodes = $recursive
      ? $this->listFolderRecursive($node)
      : $node->getDirectoryListing();

    $fileNodes = [];
    foreach ($nodes as $fileNode) {
      if ($fileNode instanceof Folder) {
        continue;
      }
      if (!FrameFile::isSupportedMime($fileNode->getMimeType())) {
        continue;
      }
      $fileNodes[] = $fileNode;
    }

    $fileIds = array_map(fn($n) => $n->getId(), $fileNodes);
    $metadatas = $this->getMetadataForFiles($fileIds);

    $files = [];
    foreach ($fileNodes as $fileNode) {
      $metadata = $metadatas[$fileNode->getId()] ?? [];
      $files[] = new FrameFile(
        $fileNode->getId(),
        $userId,
        $fileNode->getMimeType(),
        $fileNode->getMTime(),
        (int) ($metadata['capturedAt'] ?? $fileNode->getMTime()),
        $metadata['place'] ?? null,
      );
    }

    return $files;
  }

  /** @return \OCP\Files\Node[] */
  private function listFolderRecursive(Folder $folder): array
  {
    $result = [];
    foreach ($folder->getDirectoryListing() as $node) {
      if ($node instanceof Folder) {
        foreach ($this->listFolderRecursive($node) as $child) {
          $result[] = $child;
        }
      } else {
        $result[] = $node;
      }
    }
    return $result;
  }

  public function getFrameFileById(Frame $frame, int $fileId): ?FrameFile
  {
    foreach ($this->getFrameFiles($frame) as $file) {
      if ($file->getFileId() === $fileId) {
        return $file;
      }
    }
    return null;
  }

  public function createFrame(
    string $name,
    string $userUid,
    string $sources,
    string $selectionMethod,
    bool $favorNewAdditions,
    string $rotationUnit,
    int $rotationsPerUnit,
    string $startDayAt,
    string $endDayAt,
    int $imageDurationSeconds,
    string $videoDuration,
    int $videoFixedDurationSeconds,
    ?string $devicePasswordHash,
    bool $showPhotoTimestamp,
    bool $showPhotoPlace,
    bool $showClock,
    string $photoSize,
    string $backgroundType,
    string $backgroundColor,
    string $javascript
  ): Frame {
    $frame = new Frame();
    $frame->setName($name);
    $frame->setUserUid($userUid);
    $frame->setSources($sources);
    $frame->setSelectionMethod($selectionMethod);
    $frame->setFavorNewAdditions($favorNewAdditions);
    $frame->setRotationUnit($rotationUnit);
    $frame->setRotationsPerUnit($rotationsPerUnit);
    $frame->setStartDayAt($startDayAt);
    $frame->setEndDayAt($endDayAt);
    $frame->setImageDurationSeconds($imageDurationSeconds);
    $frame->setVideoDuration($videoDuration);
    $frame->setVideoFixedDurationSeconds($videoFixedDurationSeconds);
    $frame->setDevicePasswordHash($devicePasswordHash);
    $frame->setShowPhotoTimestamp($showPhotoTimestamp);
    $frame->setShowPhotoPlace($showPhotoPlace);
    $frame->setShowClock($showClock);
    $frame->setPhotoSize($photoSize);
    $frame->setBackgroundType($backgroundType);
    $frame->setBackgroundColor($backgroundColor);
    $frame->setJavascript($javascript);
    $frame->setShareToken($this->random->generate(64, ISecureRandom::CHAR_ALPHANUMERIC));
    $frame->setCreatedAt(new DateTime());

    return $this->insert($frame);
  }

  public function updateFrame(
    Frame $frame,
    string $name,
    string $sources,
    string $selectionMethod,
    bool $favorNewAdditions,
    string $rotationUnit,
    int $rotationsPerUnit,
    string $startDayAt,
    string $endDayAt,
    int $imageDurationSeconds,
    string $videoDuration,
    int $videoFixedDurationSeconds,
    ?string $devicePasswordHash,
    bool $showPhotoTimestamp,
    bool $showPhotoPlace,
    bool $showClock,
    string $photoSize,
    string $backgroundType,
    string $backgroundColor,
    string $javascript
  ): Frame {
    $frame->setName($name);
    $frame->setSources($sources);
    $frame->setSelectionMethod($selectionMethod);
    $frame->setFavorNewAdditions($favorNewAdditions);
    $frame->setRotationUnit($rotationUnit);
    $frame->setRotationsPerUnit($rotationsPerUnit);
    $frame->setStartDayAt($startDayAt);
    $frame->setEndDayAt($endDayAt);
    $frame->setImageDurationSeconds($imageDurationSeconds);
    $frame->setVideoDuration($videoDuration);
    $frame->setVideoFixedDurationSeconds($videoFixedDurationSeconds);
    if ($devicePasswordHash !== null) {
      $frame->setDevicePasswordHash($devicePasswordHash);
    }
    $frame->setShowPhotoTimestamp($showPhotoTimestamp);
    $frame->setShowPhotoPlace($showPhotoPlace);
    $frame->setShowClock($showClock);
    $frame->setPhotoSize($photoSize);
    $frame->setBackgroundType($backgroundType);
    $frame->setBackgroundColor($backgroundColor);
    $frame->setJavascript($javascript);

    return $this->update($frame);
  }

  public function destroyFrame(Frame $frame): void
  {
    $this->connection->beginTransaction();
    $frameId = $frame->getId();

    $query = $this->connection->getQueryBuilder();
    $query->delete('media_frames_entries')
      ->where($query->expr()->eq('frame_id', $query->createNamedParameter($frameId, IQueryBuilder::PARAM_INT)))
      ->executeStatement();

    $query = $this->connection->getQueryBuilder();
    $query->delete('media_frames_frames')
      ->where($query->expr()->eq('id', $query->createNamedParameter($frameId, IQueryBuilder::PARAM_INT)))
      ->executeStatement();

    $this->connection->commit();
  }

  private function getMetadataForFiles(array $fileIds): array
  {
    if (empty($fileIds)) {
      return [];
    }

    $ncDatas = [];
    foreach (array_chunk($fileIds, self::CHUNK_SIZE) as $chunk) {
      $ncDatas += $this->metadataManager->getMetadataForFiles($chunk);
    }
    $memoriesDatas = $this->getMemoriesDataForFiles($fileIds);

    $result = [];
    foreach ($fileIds as $id) {
      $capturedAt = null;
      $place = null;

      $ncData = $ncDatas[$id] ?? null;
      $memoriesData = $memoriesDatas[$id] ?? null;

      if (isset($memoriesData['exif']['DateTimeEpoch'])) {
        $capturedAt = $memoriesData['exif']['DateTimeEpoch'];
      } elseif ($ncData?->hasKey('photos-original_date_time')) {
        $capturedAt = $ncData->getInt('photos-original_date_time');
      }

      if ($ncData?->hasKey('photos-place')) {
        $place = $ncData->getString('photos-place');
      } elseif (isset($memoriesData['place']['name'])) {
        $place = $memoriesData['place']['name'];
      }

      $result[$id] = ['capturedAt' => $capturedAt, 'place' => $place];
    }

    return $result;
  }

  private function getMemoriesDataForFiles(array $fileIds): array
  {
    $result = [];

    if (!$this->connection->tableExists('memories')) {
      return $result;
    }

    try {
      foreach (array_chunk($fileIds, self::CHUNK_SIZE) as $chunk) {
        $query = $this->connection->getQueryBuilder();
        $query->select('fileid', 'exif')
          ->from('memories')
          ->where($query->expr()->in('fileid', $query->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)));
        foreach ($query->executeQuery()->fetchAll() as $row) {
          $result[$row['fileid']]['exif'] = json_decode($row['exif'], true);
        }
      }
    } catch (Exception) {
    }

    if (
      !$this->connection->tableExists('memories_places') ||
      !$this->connection->tableExists('memories_planet')
    ) {
      return $result;
    }

    try {
      foreach (array_chunk($fileIds, self::CHUNK_SIZE) as $chunk) {
        $query = $this->connection->getQueryBuilder();
        $query->select('memories.fileid', 'planet.name')
          ->from('memories', 'memories')
          ->innerJoin('memories', 'memories_places', 'places', $query->expr()->eq('memories.fileid', 'places.fileid'))
          ->innerJoin('places', 'memories_planet', 'planet', $query->expr()->eq('places.osm_id', 'planet.osm_id'))
          ->andWhere($query->expr()->in('memories.fileid', $query->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)))
          ->andWhere($query->expr()->eq('places.mark', $query->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)));
        foreach ($query->executeQuery()->fetchAll() as $row) {
          $result[$row['fileid']]['place'] = $row;
        }
      }
    } catch (Exception) {
    }

    return $result;
  }
}
