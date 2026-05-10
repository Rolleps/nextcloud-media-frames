<?php

declare(strict_types=1);

namespace OCA\MediaFrames\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getName()
 * @method string getUserUid()
 * @method string getShareToken()
 * @method string getSources()
 * @method string getSelectionMethod()
 * @method bool getFavorNewAdditions()
 * @method string getRotationUnit()
 * @method int getRotationsPerUnit()
 * @method string getStartDayAt()
 * @method string getEndDayAt()
 * @method int getImageDurationSeconds()
 * @method string getVideoDuration()
 * @method int getVideoFixedDurationSeconds()
 * @method string|null getDevicePasswordHash()
 * @method bool getShowPhotoTimestamp()
 * @method bool getShowPhotoPlace()
 * @method bool getShowClock()
 * @method string getPhotoSize()
 * @method string getBackgroundType()
 * @method string getBackgroundColor()
 * @method \DateTime getCreatedAt()
 * @method string getJavascript()
 *
 * @method void setName(string $name)
 * @method void setUserUid(string $userUid)
 * @method void setShareToken(string $shareToken)
 * @method void setSources(string $sources)
 * @method void setSelectionMethod(string $selectionMethod)
 * @method void setFavorNewAdditions(bool $favorNewAdditions)
 * @method void setRotationUnit(string $rotationUnit)
 * @method void setRotationsPerUnit(int $rotationsPerUnit)
 * @method void setStartDayAt(string $startDayAt)
 * @method void setEndDayAt(string $endDayAt)
 * @method void setImageDurationSeconds(int $seconds)
 * @method void setVideoDuration(string $videoDuration)
 * @method void setVideoFixedDurationSeconds(int $seconds)
 * @method void setDevicePasswordHash(?string $hash)
 * @method void setShowPhotoTimestamp(bool $show)
 * @method void setShowPhotoPlace(bool $show)
 * @method void setShowClock(bool $show)
 * @method void setPhotoSize(string $photoSize)
 * @method void setBackgroundType(string $backgroundType)
 * @method void setBackgroundColor(string $backgroundColor)
 * @method void setCreatedAt(\DateTime $createdAt)
 * @method void setJavascript(string $javascript)
 */
class Frame extends Entity
{
  /** @var string */
  public $name;
  /** @var string */
  public $userUid;
  /** @var string */
  public $shareToken;
  /** @var string JSON array of source objects */
  public $sources = '[]';
  /** @var string */
  public $selectionMethod;
  /** @var bool */
  public $favorNewAdditions;
  /** @var string */
  public $rotationUnit;
  /** @var int */
  public $rotationsPerUnit;
  /** @var string */
  public $startDayAt;
  /** @var string */
  public $endDayAt;
  /** @var int seconds to show each image/document page */
  public $imageDurationSeconds = 30;
  /** @var string 'full' = play to end, 'fixed' = fixed duration */
  public $videoDuration = 'full';
  /** @var int seconds, used when videoDuration = 'fixed' */
  public $videoFixedDurationSeconds = 30;
  /** @var string|null bcrypt hash; null = no device protection */
  public $devicePasswordHash;
  /** @var bool */
  public $showPhotoTimestamp;
  /** @var bool */
  public $showPhotoPlace;
  /** @var bool */
  public $showClock;
  /** @var string */
  public $photoSize;
  /** @var string */
  public $backgroundType;
  /** @var string */
  public $backgroundColor;
  /** @var string */
  public $javascript;
  /** @var \DateTime */
  public $createdAt;

  /** @var \DateTimeZone */
  public $timezone;

  public function __construct()
  {
    $this->addType('name', 'string');
    $this->addType('userUid', 'string');
    $this->addType('shareToken', 'string');
    $this->addType('sources', 'string');
    $this->addType('selectionMethod', 'string');
    $this->addType('favorNewAdditions', 'bool');
    $this->addType('rotationUnit', 'string');
    $this->addType('rotationsPerUnit', 'int');
    $this->addType('startDayAt', 'string');
    $this->addType('endDayAt', 'string');
    $this->addType('imageDurationSeconds', 'int');
    $this->addType('videoDuration', 'string');
    $this->addType('videoFixedDurationSeconds', 'int');
    $this->addType('devicePasswordHash', 'string');
    $this->addType('showPhotoTimestamp', 'bool');
    $this->addType('showPhotoPlace', 'bool');
    $this->addType('showClock', 'bool');
    $this->addType('backgroundType', 'string');
    $this->addType('backgroundColor', 'string');
    $this->addType('photoSize', 'string');
    $this->addType('javascript', 'string');
    $this->addType('createdAt', 'datetime');
  }

  public function getDecodedSources(): array
  {
    return json_decode($this->sources ?? '[]', true) ?? [];
  }

  public function setTimezone(\DateTimeZone $timezone): void
  {
    $this->timezone = $timezone;
  }

  public function getTimezone(): ?\DateTimeZone
  {
    return $this->timezone;
  }
}
