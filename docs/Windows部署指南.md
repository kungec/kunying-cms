# Windows 部署指南

坤影CMS 支持 Windows 环境(phpstudy / IIS / 独立PHP+MySQL),核心功能全部兼容。

## 一、环境要求

- Windows 7 SP1 / Server 2008 R2 及以上
- PHP 7.4 ~ 8.3(需启用扩展:`pdo_mysql` `curl` `gd` `mbstring` `zip` `fileinfo` `openssl`)
- MySQL 5.7+ / 8.0
- 推荐:phpstudy(小皮)面板,一键配齐 PHP+MySQL+Apache/Nginx

## 二、安装步骤

1. 将程序解压到站点目录(如 `D:\wwwroot\kunying`)
2. phpstudy:添加网站 → 域名填 `localhost` 或本地域名 → 根目录指向程序目录
3. 浏览器访问 `http://localhost/` 进入安装向导
4. 按向导填写 MySQL 信息(本机默认 `127.0.0.1`,账号 `root`)
5. 安装完成后 `install` 目录自动删除

> 程序默认使用 `?s=` 路由,无需任何伪静态即可完整使用。

## 三、伪静态(可选,启用 `/detail-{id}.html` 式地址)

后台「系统设置 → 伪静态」开启后,按服务器类型配置:

### phpstudy-Nginx
```nginx
location / {
    if (!-e $request_filename) {
        rewrite ^/detail-([0-9]+)-([0-9]+)\.html$ /index.php?s=/vod/detail&id=$1&ep=$2 last;
        rewrite ^/detail-([0-9]+)\.html$ /index.php?s=/vod/detail&id=$1 last;
    }
}
```

### phpstudy-Apache
程序根目录自带 `web.config` 样例(IIS直接生效),Apache 用户使用 `.htaccess`(需开启 mod_rewrite)。

### IIS
需安装 [URL Rewrite 模块](https://www.iis.net/downloads/microsoft/url-rewrite),在站点根目录放 `web.config`:
```xml
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
  <system.webServer>
    <rewrite>
      <rules>
        <rule name="detail2" stopProcessing="true">
          <match url="^detail-([0-9]+)-([0-9]+)\.html$" ignoreCase="false"/>
          <action type="Rewrite" url="index.php?s=/vod/detail&amp;id={R:1}&amp;ep={R:2}"/>
        </rule>
        <rule name="detail" stopProcessing="true">
          <match url="^detail-([0-9]+)\.html$" ignoreCase="false"/>
          <action type="Rewrite" url="index.php?s=/vod/detail&amp;id={R:1}"/>
        </rule>
      </rules>
    </rewrite>
  </system.webServer>
</configuration>
```

## 四、定时任务(Windows 任务计划程序)

| 任务 | 命令(假设站点在 D:\wwwroot\kunying,PHP在 D:\php\php.exe) |
|---|---|
| 定时采集(每30分钟) | `D:\php\php.exe D:\wwwroot\kunying\ky\cron\collect.php` |
| 整库多进程采集 | `D:\php\php.exe D:\wwwroot\kunying\ky\cron\collect.php --full --pages=30 --threads=4` |
| 封面压缩收尾 | `D:\php\php.exe D:\wwwroot\kunying\ky\cron\imgopt.php --limit=2000` |
| 数据库备份 | `D:\php\php.exe D:\wwwroot\kunying\ky\cron\backup.php` |

创建方式(管理员CMD,一条创建每日03:20备份):
```cmd
schtasks /create /tn "KunYingBackup" /sc daily /st 03:20 /tr "D:\php\php.exe D:\wwwroot\kunying\ky\cron\backup.php"
```

> 数据库备份为纯PHP实现(免mysqldump依赖),Windows/Linux通用,
> 输出到 `data/backup/`,默认保留最近7份。

## 五、Windows 与 Linux 差异说明

| 项目 | 说明 |
|---|---|
| 多进程采集 `--threads` | ✅ 支持(空设备已适配 NUL) |
| 数据库备份 backup.php | ✅ 纯PHP跨平台 |
| db_backup.sh / healthcheck.sh | ❌ Linux专用(Windows用任务计划+backup.php替代) |
| 权限 chmod | Windows下自动忽略,无需处理 |
| 目录分隔符 | 代码内已全部使用 `/`,Windows 原生兼容 |

## 六、常见问题

**Q: 验证码图片不显示?**
GD扩展未启用,phpstudy → PHP设置 → 勾选 gd。

**Q: 页面报 ZipArchive 不存在?**
zip扩展未启用,勾选 zip。

**Q: 提示目录不可写?**
右键目录 → 属性 → 安全 → 给 IIS_IUSRS(或网站运行账户)添加「修改」权限。
