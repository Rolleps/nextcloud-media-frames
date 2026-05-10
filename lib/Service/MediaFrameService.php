<?php

declare(strict_types=1);

namespace OCA\MediaFrames\Service;

use OCA\MediaFrames\Db\Entry;
use OCA\MediaFrames\Db\EntryMapper;
use OCA\MediaFrames\Db\Frame;
use OCA\MediaFrames\Db\FrameFile;
use OCA\MediaFrames\Db\FrameMapper;
use OCP\Files\IRootFolder;
use OCP\Files\Node;

/**
 * @psalm-suppress UnusedClass
 */
class MediaFrameService
{
  private EntryMapper $entryMapper;
  private FrameMapper $frameMapper;
  private IRootFolder $rootFolder;
  private Frame $frame;

  public function __construct(
    EntryMapper $entryMapper,
    FrameMapper $frameMapper,
    IRootFolder $rootFolder,
    Frame $frame,
  ) {
    $this->entryMapper = $entryMapper;
    $this->frameMapper = $frameMapper;
    $this->rootFolder = $rootFolder;
    $this->frame = $frame;
  }

  public function getCurrentFrameFile(): ?FrameFile
  {
    $latestEntry = $this->entryMapper->getLatestEntry($this->frame->getId());

    if ($latestEntry && !$this->entryExpired($latestEntry)) {
      $frameFile = $this->getFrameFileById($latestEntry->getFileId());
      if ($frameFile) {
        $frameFile->setExpiresAt($this->getEntryExpiry($latestEntry));
        return $frameFile;
      }
    }

    $picked = $this->pickNewFrameFile();
    if (!$picked) {
      return null;
    }

    $entry = $this->entryMapper->createEntry($picked->getFileId(), $this->frame->getId());
    $picked->setExpiresAt($this->getEntryExpiry($entry));
    return $picked;
  }

  public function getEntryExpiry(Entry $entry): \DateTime
  {
    $mediaType = $this->getMediaTypeForEntry($entry);

    // Videos expire far in the future — the client advances when the video ends
    if ($mediaType === FrameFile::MEDIA_TYPE_VIDEO) {
      return (new \DateTime())->modify('+1 year');
    }

    // Documents and images use the configured duration
    $durationSeconds = $this->frame->getImageDurationSeconds();
    return (clone $entry->getCreatedAt())->modify("+{$durationSeconds} seconds");
  }

  private function getMediaTypeForEntry(Entry $entry): string
  {
    $file = $this->getFrameFileById($entry->getFileId());
    return $file ? $file->getMediaType() : FrameFile::MEDIA_TYPE_IMAGE;
  }

  private function entryExpired(Entry $entry): bool
  {
    return $this->getEntryExpiry($entry) <= new \DateTime();
  }

  private function pickNewFrameFile(): ?FrameFile
  {
    $usedFileIds = $this->entryMapper->getUsedFileIds($this->frame->getId());
    $frameFiles = $this->frameMapper->getFrameFiles($this->frame);
    $available = array_values(array_filter($frameFiles, fn($f) => !in_array($f->getFileId(), $usedFileIds)));

    if (count($available) === 0) {
      $this->entryMapper->deleteFrameEntries($this->frame->getId());
      $available = $frameFiles;
    }

    if (empty($available)) {
      return null;
    }

    $sorted = $this->sortBySelectionMethod($available);

    if ($this->frame->getFavorNewAdditions()) {
      $sorted = $this->favorNewAdditions($sorted);
    }

    return $sorted[0];
  }

  private function favorNewAdditions(array $files): array
  {
    $cutAt = (new \DateTime())->modify('-1 week')->getTimestamp();
    $fresh = [];
    $rest = [];

    foreach ($files as $file) {
      if ($file->getAddedAtTimestamp() > $cutAt) {
        $fresh[] = $file;
      } else {
        $rest[] = $file;
      }
    }

    return array_merge($fresh, $rest);
  }

  private function sortBySelectionMethod(array $files): array
  {
    switch ($this->frame->getSelectionMethod()) {
      case FrameMapper::SELECTION_METHOD_LATEST:
      case FrameMapper::SELECTION_METHOD_OLDEST:
        usort($files, function ($a, $b) {
          $diff = $b->getCapturedAtTimestamp() - $a->getCapturedAtTimestamp();
          return $diff !== 0 ? $diff : $b->getAddedAtTimestamp() - $a->getAddedAtTimestamp();
        });
        return $this->frame->getSelectionMethod() === FrameMapper::SELECTION_METHOD_LATEST
          ? $files
          : array_reverse($files);

      case FrameMapper::SELECTION_METHOD_RANDOM:
        shuffle($files);
        return $files;
    }

    return $files;
  }

  private function getFrameFileById(int $fileId): ?FrameFile
  {
    return $this->frameMapper->getFrameFileById($this->frame, $fileId);
  }

  public function getFrameFileNode(FrameFile $frameFile): Node
  {
    $nodes = $this->rootFolder
      ->getUserFolder($frameFile->getUserUid())
      ->getById($frameFile->getFileId());

    return current($nodes);
  }
}
