<?php

namespace csabourin\stamppassport\web\assets\frontend;

use craft\web\AssetBundle;

class FrontendAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = __DIR__;

        $this->css = [
            'css/passport.css',
        ];

        $this->js = [
            'js/passport.js',
        ];

        parent::init();
    }
}
