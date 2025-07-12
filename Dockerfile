FROM ubuntu:22.04

ENV DEBIAN_FRONTEND=noninteractive

# === 1. Install dependencies ===
RUN apt-get update; \
    apt-get install -y busybox-static; \
    apt-get install -y \
      build-essential \
      musl-tools \
      gcc \
      git \
      curl \
      xz-utils \
      busybox \
      qemu-system-x86 \
      grub-pc-bin xorriso \
      cpio \
      libncurses-dev \
      flex \
      bison \
      libssl-dev \
      bc \
      file \
      tree \
      rsync \
      zstd \
      libelf-dev \
      libonig-dev \
      libonig5 \
      libxml2-dev \
      automake \
      libtool \
      pkg-config \
      re2c; \
    rm -rf /var/lib/apt/lists/*

# === 2. Set working directory ===
WORKDIR /build

# === 3. Build kernel and PHP ===
ENV KERNEL_VERSION=6.6.1
ENV PHP_VERSION=8.3.0
ENV BUILD_DIR=/build
ENV OUT_DIR=/build/out
ENV ROOTFS_DIR=$BUILD_DIR/rootfs
ENV KERNEL_DIR=$BUILD_DIR/linux
ENV PHP_DIR=$BUILD_DIR/php
ENV INITRAMFS_FILE=$OUT_DIR/initramfs.cpio.gz
ENV ISO_DIR=$BUILD_DIR/iso
ENV ISO_FILE=$OUT_DIR/php-linux.iso

RUN set -eux

RUN mkdir -p "$BUILD_DIR" "$OUT_DIR" "$ROOTFS_DIR/bin" "$ROOTFS_DIR/dev" "$ROOTFS_DIR/proc" "$ROOTFS_DIR/sys";

# Download and build Linux kernel
RUN cd "$BUILD_DIR"; \
    curl -LO https://cdn.kernel.org/pub/linux/kernel/v6.x/linux-$KERNEL_VERSION.tar.xz; \
    tar -xvf linux-$KERNEL_VERSION.tar.xz; \
    mv linux-$KERNEL_VERSION linux
RUN cd "$KERNEL_DIR"; \
    make defconfig; \
    scripts/config --enable CONFIG_FB; \
    scripts/config --enable CONFIG_FB_VESA; \
    scripts/config --enable CONFIG_FB_SIMPLE; \
    scripts/config --enable CONFIG_FB_DEFERRED_IO; \
    scripts/config --enable CONFIG_DRM; \
    scripts/config --enable CONFIG_DRM_VMWGFX; \
    scripts/config --enable CONFIG_DRM_KMS_HELPER; \
    scripts/config --enable CONFIG_DRM_FBDEV_EMULATION; \
    make -j"$(nproc)" bzImage; \
    cp arch/x86/boot/bzImage "$OUT_DIR/vmlinuz";

# Download and build PHP
#RUN cd "$BUILD_DIR"; \
#    curl -LO https://www.php.net/distributions/php-${PHP_VERSION}.tar.xz; \
#    tar -xf php-${PHP_VERSION}.tar.xz; \
#    mv php-${PHP_VERSION} php
#RUN cd "$PHP_DIR"; \
#    export CC="musl-gcc"; \
#    export CFLAGS="-static"; \
#    export CXXFLAGS="-static"; \
#    export LDFLAGS="-Bstatic"; \
#    ./buildconf --force; \
#    ./configure \
#      --disable-all \
#      --enable-cli \
#      --disable-shared \
#      --disable-cgi  \
#      --disable-fpm \
#      --prefix="$PHP_DIR/build"
#RUN cd "$PHP_DIR"; \
#    export CC="musl-gcc"; \
#    export CFLAGS="-static"; \
#    export CXXFLAGS="-static"; \
#    export LDFLAGS="-Bstatic"; \
#    make clean; \
#    make -j"$(nproc)"; \
#    make install
#RUN cd $ROOTFS_DIR/bin; \
#    cp "$PHP_DIR/build/bin/php" php; \
#    chmod +x php; \
#    mkdir -p "$ROOTFS_DIR/lib"; \
#    cp "/usr/lib/x86_64-linux-gnu/linux-vdso.so.1" "$ROOTFS_DIR/lib/" # TODO

# Set up busybox shell
RUN cd $ROOTFS_DIR/bin; \
    cp /bin/busybox busybox; \
    chmod +x busybox; \
    cp /bin/busybox sh; \
    chmod +x sh; \
    mkdir -p "$ROOTFS_DIR/lib/x86_64-linux-gnu"; \
    mkdir -p "$ROOTFS_DIR/lib64"; \
    cp "/usr/lib/x86_64-linux-gnu/libresolv.so.2" "$ROOTFS_DIR/lib/x86_64-linux-gnu/"; \
    cp "/usr/lib/x86_64-linux-gnu/libc.so.6" "$ROOTFS_DIR/lib/x86_64-linux-gnu/"; \
    cp "/usr/lib/x86_64-linux-gnu/ld-linux-x86-64.so.2" "$ROOTFS_DIR/lib64/"; \
    cd $ROOTFS_DIR; \
    cp /bin/busybox init; \
    chmod +x init

# Init script
#COPY "init.sh" "$ROOTFS_DIR/init"
#RUN chmod +x "$ROOTFS_DIR/init"
COPY "init.php" "$ROOTFS_DIR/init.php"

# Create initramfs
RUN cd "$ROOTFS_DIR"; \
    find . -print0 | cpio --null -ov --format=newc | gzip -9 > "$INITRAMFS_FILE"

# Create bootable ISO
RUN cd "$ROOTFS_DIR"; \
    mkdir -p "$ISO_DIR/boot/grub"; \
    cp "$OUT_DIR/vmlinuz" "$ISO_DIR/boot/vmlinuz"; \
    cp "$INITRAMFS_FILE" "$ISO_DIR/boot/initramfs.gz"; \
    echo 'timeout=0' > "$ISO_DIR/boot/grub/grub.cfg"; \
    echo 'default=0' >> "$ISO_DIR/boot/grub/grub.cfg"; \
    echo '' >> "$ISO_DIR/boot/grub/grub.cfg"; \
    echo 'menuentry "PHP Linux" {' >> "$ISO_DIR/boot/grub/grub.cfg"; \
    echo '  linux /boot/vmlinuz loglevel=7 console=tty0' >> "$ISO_DIR/boot/grub/grub.cfg"; \
    echo '  initrd /boot/initramfs.gz' >> "$ISO_DIR/boot/grub/grub.cfg"; \
    echo '}' >> "$ISO_DIR/boot/grub/grub.cfg"; \
    grub-mkrescue -o "$ISO_FILE" "$ISO_DIR" 2>/dev/null

# === 4. Default CMD ===
CMD ["/bin/bash"]
