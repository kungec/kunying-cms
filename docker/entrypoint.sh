#!/bin/bash
# 坤影CMS Docker入口
set -e

# 初始化缺失目录
mkdir -p /var/www/html/data/cache /var/www/html/data/upload /var/www/html/data/license
chown -R www-data:www-data /var/www/html/data /var/www/html/theme /var/www/html/addon

# 应用nginx配置并启动PHP-FPM
cp /var/www/html/docker/nginx.conf /etc/nginx/http.d/default.conf
php-fpm -D

# 前台nginx
exec nginx -g "daemon off;"
