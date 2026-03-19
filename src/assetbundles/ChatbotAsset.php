<?php
namespace jelle\craftjsonplugin\assetbundles;

use craft\web\AssetBundle;

class ChatbotAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = '@jelle/craftjsonplugin/assetbundles/dist';
        $this->css = ['css/chatbot.css'];
        $this->js = ['js/chunk.js', 'js/chatbot.js'];
        parent::init();
    }
}
