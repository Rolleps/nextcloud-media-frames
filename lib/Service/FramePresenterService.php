<?php

declare(strict_types=1);

namespace OCA\MediaFrames\Service;

use OCP\IURLGenerator;
use OCA\MediaFrames\Db\Frame;

class FramePresenterService
{
  private IURLGenerator $urlGenerator;

  public function __construct(IURLGenerator $urlGenerator)
  {
    $this->urlGenerator = $urlGenerator;
  }

  public function presentFrame(Frame $frame): array
  {
    $data = [
      'id' => $frame->getId(),
      'name' => $frame->getName(),
      'shareToken' => $frame->getShareToken(),
      'sources' => $frame->getDecodedSources(),
      'selectionMethod' => $frame->getSelectionMethod(),
      'favorNewAdditions' => $frame->getFavorNewAdditions(),
      'rotationUnit' => $frame->getRotationUnit(),
      'rotationsPerUnit' => $frame->getRotationsPerUnit(),
      'startDayAt' => $frame->getStartDayAt(),
      'endDayAt' => $frame->getEndDayAt(),
      'imageDurationSeconds' => $frame->getImageDurationSeconds(),
      'videoDuration' => $frame->getVideoDuration(),
      'videoFixedDurationSeconds' => $frame->getVideoFixedDurationSeconds(),
      'hasDevicePassword' => $frame->getDevicePasswordHash() !== null,
      'showPhotoTimestamp' => $frame->getShowPhotoTimestamp(),
      'showPhotoPlace' => $frame->getShowPhotoPlace(),
      'showClock' => $frame->getShowClock(),
      'photoSize' => $frame->getPhotoSize(),
      'backgroundType' => $frame->getBackgroundType(),
      'backgroundColor' => $frame->getBackgroundColor(),
      'javascript' => $frame->getJavascript(),
    ];

    $data['urls'] = [
      'show' => $this->urlGenerator->linkToRoute('media_frames.page.mediaframe', ['shareToken' => $frame->getShareToken()]),
      'edit' => $this->urlGenerator->linkToRoute('media_frames.page.edit', ['id' => $frame->getId()]),
      'update' => $this->urlGenerator->linkToRoute('media_frames.page.update', ['id' => $frame->getId()]),
      'destroy' => $this->urlGenerator->linkToRoute('media_frames.page.destroy', ['id' => $frame->getId()]),
    ];

    return $data;
  }

  public function presentFrames(array $frames): array
  {
    return array_map(fn($frame) => $this->presentFrame($frame), $frames);
  }
}
