FROM ubuntu:22.04

ENV DEBIAN_FRONTEND=noninteractive

# Set up basics
ENV KERNEL_VERSION=6.6.1
ENV PHP_VERSION=8.4.0
ENV BUILD_DIR=/build
ENV OUT_DIR=/build/out
ENV ROOTFS_DIR=$BUILD_DIR/rootfs
ENV KERNEL_DIR=$BUILD_DIR/linux
ENV PHP_DIR=$BUILD_DIR/php
ENV INITRAMFS_FILE=$OUT_DIR/initramfs.cpio.gz
ENV ISO_DIR=$BUILD_DIR/iso
ENV ISO_FILE=$OUT_DIR/php-linux.iso
WORKDIR /build
CMD ["/bin/bash"]
COPY "rootfs/" "$ROOTFS_DIR/"
RUN set -eux \
 && mkdir -p "$OUT_DIR" \
 && rm -rf "$ROOTFS_DIR/**/.gitkeep" \
 && find "$ROOTFS_DIR/bin/" -type f -exec chmod +x {} +

# Install tools and dependencies
COPY "tools/" "/tools/"
RUN apt-get update \
 && apt-get install -y \
      automake \
      bc \
      bison \
      build-essential \
      cpio \
      curl \
      file \
      flex \
      gcc \
      git \
      grub-pc-bin \
      libcurl4-openssl-dev \
      libelf-dev \
      libffi-dev \
      libiconv-hook-dev \
      libjpeg-dev \
      libncurses-dev \
      libonig-dev \
      libonig5 \
      libpng-dev \
      libreadline-dev \
      libsqlite3-dev \
      libssl-dev \
      libtidy-dev \
      libtool \
      libxml2-dev \
      libxslt-dev \
      libzip-dev \
      php \
      pkg-config \
      re2c \
      sqlite3 \
      tree \
      xorriso \
      xz-utils \
      zstd \
 && rm -rf /var/lib/apt/lists/*

# Download and build Linux kernel
RUN cd "$BUILD_DIR" \
 && curl -LO https://cdn.kernel.org/pub/linux/kernel/v6.x/linux-$KERNEL_VERSION.tar.xz \
 && tar -xvf linux-$KERNEL_VERSION.tar.xz \
 && mv linux-$KERNEL_VERSION linux
RUN cd "$KERNEL_DIR" \
 && make defconfig \
 && scripts/config --enable CONFIG_FB \
 && scripts/config --enable CONFIG_FB_VESA \
 && scripts/config --enable CONFIG_FB_SIMPLE \
 && scripts/config --enable CONFIG_FB_DEFERRED_IO \
 && scripts/config --enable CONFIG_DRM \
 && scripts/config --enable CONFIG_DRM_VMWGFX \
 && scripts/config --enable CONFIG_DRM_KMS_HELPER \
 && scripts/config --enable CONFIG_DRM_FBDEV_EMULATION \
 && make -j"$(nproc)" bzImage \
 && cp arch/x86/boot/bzImage "$OUT_DIR/vmlinuz"

# Download and build PHP
RUN cd "$BUILD_DIR" \
 && curl -LO https://www.php.net/distributions/php-${PHP_VERSION}.tar.xz \
 && tar -xf php-${PHP_VERSION}.tar.xz \
 && mv php-${PHP_VERSION} php
RUN cd "$PHP_DIR" \
 && ./buildconf --force \
 && php /tools/patch-php-cli.php "$PHP_DIR/sapi/cli/php_cli.c" \
 && ./configure \
      --disable-all \
      --disable-cgi \
      --disable-fpm \
      --disable-shared \
      --enable-cli \
      --enable-dom \
      --enable-filter \
      --enable-json \
      --enable-pcntl \
      --enable-pdo \
      --enable-phar \
      --enable-posix \
      --enable-zip \
      --with-apcu \
      --with-config-file-path=/etc/ \
      --with-curl \
      --with-dom \
      --with-gd \
      --with-ffi \
      --with-iconv \
      --with-jpeg \
      --with-mbstring \
      --with-libxml \
      --with-opcache \
      --with-openssl \
      --with-pdo \
      --with-pdo-sqlite \
      --with-png \
      --with-readline \
      --with-sqlite3 \
      --with-tidy \
      --with-xsl \
      --prefix="$PHP_DIR/build"
RUN cd "$PHP_DIR" \
 && make -j"$(nproc)" \
 && make install
RUN cd $ROOTFS_DIR \
 && cp "$PHP_DIR/build/bin/php" "$ROOTFS_DIR/bin/php" \
 && chmod +x "$ROOTFS_DIR/bin/php" \
 && sh /tools/copy-shared-libs.sh "$ROOTFS_DIR/bin/php" "$ROOTFS_DIR"

# Download and install Composer
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
 && php -r "if (hash_file('sha384', 'composer-setup.php') === 'dac665fdc30fdd8ec78b38b9800061b4150413ff2e3b6f88543c636f7cd84f6db9189d43a81e5503cda447da73c7e5b6') { echo 'Installer verified'.PHP_EOL; } else { echo 'Installer corrupt'.PHP_EOL; unlink('composer-setup.php'); exit(1); }" \
 && php composer-setup.php \
 && php -r "unlink('composer-setup.php');" \
 && mv composer.phar "$ROOTFS_DIR/bin/composer"

# Set up PHP kernel
COPY "src/" "$ROOTFS_DIR/src/"
COPY "composer.*" "$ROOTFS_DIR/"
COPY "vendor/" "$ROOTFS_DIR/vendor/"
RUN cd "$ROOTFS_DIR" \
 && ln -sf ./bin/php "$ROOTFS_DIR/init" \
 && bin/php bin/composer validate --ansi --check-lock --with-dependencies --strict \
 && bin/php bin/composer install --ansi --no-progress --no-dev \
 && bin/php bin/composer dump-autoload --optimize --apcu

# Create initramfs
RUN cd "$ROOTFS_DIR" \
 && find . -print0 | cpio --null -ov --format=newc | gzip -9 > "$INITRAMFS_FILE"

# Create bootable ISO
RUN cd "$ROOTFS_DIR" \
 && mkdir -p "$ISO_DIR/boot/grub" \
 && cp "$OUT_DIR/vmlinuz" "$ISO_DIR/boot/vmlinuz" \
 && cp "$INITRAMFS_FILE" "$ISO_DIR/boot/initramfs.gz" \
 && echo 'timeout=0' > "$ISO_DIR/boot/grub/grub.cfg" \
 && echo 'default=0' >> "$ISO_DIR/boot/grub/grub.cfg" \
 && echo '' >> "$ISO_DIR/boot/grub/grub.cfg" \
 && echo 'menuentry "PHP Linux" {' >> "$ISO_DIR/boot/grub/grub.cfg" \
 && echo '  linux /boot/vmlinuz loglevel=7 console=tty0' >> "$ISO_DIR/boot/grub/grub.cfg" \
 && echo '  initrd /boot/initramfs.gz' >> "$ISO_DIR/boot/grub/grub.cfg" \
 && echo '}' >> "$ISO_DIR/boot/grub/grub.cfg" \
 && grub-mkrescue -o "$ISO_FILE" "$ISO_DIR" 2>/dev/null
