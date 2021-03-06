<?php

namespace App\Catrobat\Services\CatrobatCodeParser\Bricks;

use App\Catrobat\Services\CatrobatCodeParser\Constants;

class ThinkForBubbleBrick extends Brick
{
  protected function create(): void
  {
    $this->type = Constants::THINK_FOR_BUBBLE_BRICK;
    $this->caption = 'Think _ for _ seconds';
    $this->setImgFile(Constants::LOOKS_BRICK_IMG);
  }
}
