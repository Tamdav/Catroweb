<?php

namespace App\Catrobat\Services\CatrobatCodeParser\Bricks;

use App\Catrobat\Services\CatrobatCodeParser\Constants;

class PrevLookBrick extends Brick
{
  protected function create(): void
  {
    $this->type = Constants::PREV_LOOK_BRICK;
    $this->caption = 'Previous look';
    $this->setImgFile(Constants::LOOKS_BRICK_IMG);
  }
}
