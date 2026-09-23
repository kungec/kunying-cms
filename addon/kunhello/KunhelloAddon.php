<?php
/** 示例插件入口:坤影CMS插件规范 */
class KunhelloAddon
{
    public function meta(): array
    {
        return ['name' => '你好·坤影', 'version' => '1.0.0'];
    }

    public function dashboardTip(): string
    {
        return '你好·坤影插件已启用,当前站点时间:' . date('Y-m-d H:i');
    }
}
