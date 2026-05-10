<?php

declare(strict_types=1);

namespace OCA\MediaFrames\Db;

class FrameFile implements \JsonSerializable
{
  public const MEDIA_TYPE_IMAGE = 'image';
  public const MEDIA_TYPE_VIDEO = 'video';
  public const MEDIA_TYPE_DOCUMENT = 'document';

  private static array $videoMimes = [
    'video/mp4', 'video/webm', 'video/quicktime', 'video/avi',
    'video/x-msvideo', 'video/mpeg', 'video/ogg', 'video/3gpp',
    'video/x-matroska', 'video/x-flv',
  ];

  private static array $documentMimes = [
    'application/pdf',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'application/vnd.oasis.opendocument.presentation',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.oasis.opendocument.spreadsheet',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  ];

  public int $fileId;
  public string $userUid;
  public string $mimeType;
  public int $addedAtTimestamp;
  public int $capturedAtTimestamp;
  public ?\DateTime $expiresAt = null;
  public ?string $place;

  public function __construct(
    int $fileId,
    string $userUid,
    string $mimeType,
    int $addedAtTimestamp,
    int $capturedAtTimestamp,
    ?string $place
  ) {
    $this->fileId = $fileId;
    $this->userUid = $userUid;
    $this->mimeType = $mimeType;
    $this->addedAtTimestamp = $addedAtTimestamp;
    $this->capturedAtTimestamp = $capturedAtTimestamp;
    $this->place = $place;
  }

  public static function isSupportedMime(string $mimeType): bool
  {
    return str_starts_with($mimeType, 'image/')
      || in_array($mimeType, self::$videoMimes)
      || in_array($mimeType, self::$documentMimes);
  }

  public function getMediaType(): string
  {
    if (in_array($this->mimeType, self::$videoMimes)) {
      return self::MEDIA_TYPE_VIDEO;
    }
    if (in_array($this->mimeType, self::$documentMimes)) {
      return self::MEDIA_TYPE_DOCUMENT;
    }
    return self::MEDIA_TYPE_IMAGE;
  }

  public function getFileId(): int { return $this->fileId; }
  public function getUserUid(): string { return $this->userUid; }
  public function getMimeType(): string { return $this->mimeType; }
  public function getAddedAtTimestamp(): int { return $this->addedAtTimestamp; }
  public function getCapturedAtTimestamp(): int { return $this->capturedAtTimestamp; }
  public function getPlace(): ?string { return $this->place; }

  public function setExpiresAt(\DateTime $expiresAt): void
  {
    $this->expiresAt = $expiresAt;
  }

  public function getExpiresAt(): ?\DateTime
  {
    return $this->expiresAt;
  }

  public function getExpiresHeader(): string
  {
    $gmt = new \DateTimeZone('GMT');
    $expiresGMT = (clone $this->expiresAt)->setTimezone($gmt);
    return $expiresGMT->format(\DateTimeInterface::RFC7231);
  }

  public function jsonSerialize(): mixed
  {
    return [
      'expiresAt' => $this->expiresAt?->format(\DateTimeInterface::ISO8601),
      'capturedAtTimestamp' => $this->capturedAtTimestamp,
    ];
  }
}
