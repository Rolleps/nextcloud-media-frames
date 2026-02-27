<?php

declare(strict_types=1);

namespace OCA\PhotoFrames\Service;

use OCP\IURLGenerator;
use OCA\PhotoFrames\Db\Frame;

class FramePresenterService
{
  private IURLGenerator $urlGenerator;

  public function __construct(
    IURLGenerator $urlGenerator,
  ) {
    $this->urlGenerator = $urlGenerator;
  }

  public function presentFrame(Frame $frame)
  {
    $data = (array) $frame;
    $data['urls'] = [
      'show' => $this->urlGenerator->linkToRoute('photo_frames.page.photoframe', ['shareToken' => $frame->getShareToken()]),
      'edit' => $this->urlGenerator->linkToRoute('photo_frames.page.edit', ['id' => $frame->getId()]),
      'update' => $this->urlGenerator->linkToRoute('photo_frames.page.update', ['id' => $frame->getId()]),
      'destroy' => $this->urlGenerator->linkToRoute('photo_frames.page.destroy', ['id' => $frame->getId()]),
    ];
    return $data;
  }

  public function presentFrames(array $frames)
  {
    return array_map(function ($frame) {
      return $this->presentFrame($frame);
    }, $frames);
  }
}
