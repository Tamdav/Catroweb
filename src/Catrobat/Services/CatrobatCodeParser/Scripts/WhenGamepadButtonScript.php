<?php

namespace App\Catrobat\Services\CatrobatCodeParser\Scripts;

use App\Catrobat\Services\CatrobatCodeParser\Constants;

class WhenGamepadButtonScript extends Script
{
  protected function create(): void
  {
    $this->type = Constants::WHEN_GAME_PAD_BUTTON_SCRIPT;
    $this->caption = 'When gamepad button _ pressed';
    $this->setImgFile(Constants::CONTROL_SCRIPT_IMG);
  }
}
