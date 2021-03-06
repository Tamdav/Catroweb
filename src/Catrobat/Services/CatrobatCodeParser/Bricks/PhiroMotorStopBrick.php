<?php

namespace App\Catrobat\Services\CatrobatCodeParser\Bricks;

use App\Catrobat\Services\CatrobatCodeParser\Constants;

class PhiroMotorStopBrick extends Brick
{
  protected function create(): void
  {
    $this->type = Constants::PHIRO_MOTOR_STOP_BRICK;
    $this->caption = 'Stop Phiro motor';
    $this->setImgFile(Constants::PHIRO_BRICK_IMG);
  }
}
