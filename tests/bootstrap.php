<?php

// The Anchors service only needs Craft's/Yii's helpers, not a running app.
// Html::encode() and models reference the Yii base class, so load it.
require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/vendor/yiisoft/yii2/Yii.php';
