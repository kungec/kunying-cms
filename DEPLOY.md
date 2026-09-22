# 坤影CMS 部署说明

## 方式一:宝塔面板(推荐小白)
1. 宝塔面板 → 网站 → 添加站点(绑定你的域名,创建数据库)
2. 上传本程序zip到站点根目录并解压
3. 访问 http://域名/install/ 按向导一键安装
4. 建议nginx伪静态规则:
```
location ^~ /ky/ { deny all; return 404; }
location ^~ /data/ { deny all; return 404; }
location ~* ^/(theme|addon)/.*\.php$ { deny all; return 404; }
location / { try_files $uri $uri/ /index.php?s=$uri&$query_string; }
location ~ \.php(/|$) { try_files $uri =404; fastcgi_pass unix:/tmp/php-cgi-82.sock; include fastcgi_params; fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; }
```
(php-cgi sock名称以宝塔实际为准)
5. Cloudflare开启小云朵+严格模式时,后台「系统设置→CDN加速」选择"已开启CDN"

## 方式二:Docker一键部署
```bash
cd kunying
docker compose up -d
# 访问 http://服务器IP:8080 进入安装向导
# 数据库: 主机=db 密码=kunying_root_2026 库名=kunying
```

## 环境要求
- PHP 7.4+ (推荐8.0-8.2),扩展: pdo_mysql curl gd mbstring zip fileinfo openssl
- MySQL 5.6+ / MariaDB / 8.0

## 安装后
- 后台: /admin.php(立即修改默认密码)
- 影视站授权控制端(kunying-api)以相同方式部署在另一站点
