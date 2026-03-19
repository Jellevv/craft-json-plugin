<?php
namespace jelle\craftjsonplugin\assetbundles;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class DashboardAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = '@jelle/craftjsonplugin/assetbundles/dist';
        $this->jsOptions = ['type' => 'module'];
        $this->depends = [CpAsset::class];
        $this->css = ['css/dashboard.css'];
        $this->js = ['js/chunk.js', 'js/dashboard.js'];
        parent::init();
    }
}
