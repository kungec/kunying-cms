# 坤影CMS Docker一键部署镜像
FROM php:8.2-fpm-alpine

# 安装nginx与PHP扩展(curl/mbstring官方镜像已内置;zip供插件/升级包解压)
RUN apk add --no-cache nginx zip unzip curl bash libzip-dev \
    && docker-php-ext-install pdo_mysql gd opcache pcntl zip

# gd扩展依赖
RUN apk add --no-cache libpng-dev libjpeg-turbo-dev freetype-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd \
    || apk add --no-cache libpng libjpeg-turbo freetype

WORKDIR /var/www/html
COPY . /var/www/html/

# 目录权限
RUN mkdir -p data/cache data/upload data/license data/backup theme addon upload/vod \
    && chmod -R 755 /var/www/html \
    && chown -R www-data:www-data data theme addon upload \
    && chmod +x docker/entrypoint.sh

EXPOSE 80
CMD ["/docker/entrypoint.sh"]
