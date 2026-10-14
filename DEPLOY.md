# 坤影CMS 部署说明

## 方式一:宝塔面板(推荐小白)
1. 宝塔面板 → 网站 → 添加站点(绑定你的域名,创建数据库)
2. 上传本程序zip到站点根目录并解压
3. 访问 http://域名/install/ 按向导一键安装(装完会自动锁安装器并尝试删除install目录)
4. 建议nginx伪静态规则:
```
location ^~ /ky/ { deny all; return 404; }
location ^~ /data/ { deny all; return 404; }
location ~* ^/(theme|addon)/.*\.php$ { deny all; return 404; }
location ~* ^/[^/]+\.(md|sql|gz|log|sh|lock|yml|yaml|ini|bak)$ { deny all; return 404; }
location / { try_files $uri $uri/ /index.php?s=$uri&$query_string; }
location ~ \.php(/|$) { try_files $uri =404; fastcgi_pass unix:/tmp/php-cgi-82.sock; include fastcgi_params; fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; }
```
(php-cgi sock名称以宝塔实际为准)
5. Cloudflare开启小云朵+严格模式时,后台「系统设置→CDN加速」选择"已开启CDN"

## 方式二:Docker一键部署
```bash
cd kunying
cp .env.example .env      # 必须先修改数据库密码(仓库不提供默认密码)
vi .env
docker compose up -d
# 访问 http://服务器IP:8080 进入安装向导
# 数据库: 主机=db 密码=你在.env里设置的MYSQL_ROOT_PASSWORD 库名=kunying
```
生产建议:在容器前加一层反向代理(nginx/caddy)启用HTTPS,不要把8080直接暴露公网。
TLS示例(caddy自动申请证书,新建docker-compose.tls.yml):
```yaml
services:
  caddy:
    image: caddy:2
    ports: ["80:80", "443:443"]
    volumes:
      - ./Caddyfile:/etc/caddy/Caddyfile
      - caddydata:/data
    depends_on: [web]
    restart: unless-stopped
volumes:
  caddydata:
```
Caddyfile:
```
你的域名.com {
    reverse_proxy web:80
}
```
部署后 `docker compose -f docker-compose.tls.yml up -d`,并把后台「固定站点域名」设为你的域名。

## 升级后自检
```bash
php ky/cron/selftest.php
```
检查PHP扩展、数据表结构、加密主题密钥完整性、授权配置、SSRF防护与目录权限,用于升级后或密钥轮换后的快速验收(可挂cron做日常监控)。

## 环境要求
- PHP 7.4+ (推荐8.0-8.2),扩展: pdo_mysql curl gd mbstring zip fileinfo openssl
- MySQL 5.6+ / MariaDB / 8.0

## 安装后
- 后台: /admin.php(立即修改默认密码)
- 强烈建议在后台「系统设置」配置站点域名(site_domain):固定外链与支付回调域名,防Host头污染
- 影视站授权控制端(kunying-api)以相同方式部署在另一站点
