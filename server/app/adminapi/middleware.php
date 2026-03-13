<?php
// adminapi 应用中间件定义
// 注意：MultiApp::loadApp() 会在全局中间件之后重置语言为默认值
// 因此需要在应用级中间件中重新加载语言包，确保多语言检测生效
return [
    \think\middleware\LoadLangPack::class,
];
