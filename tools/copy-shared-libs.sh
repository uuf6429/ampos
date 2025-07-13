#!/bin/bash

set -e

if [ "$#" -ne 2 ]; then
  echo "Usage: $0 <executable_path> <rootfs_dir>"
  exit 1
fi

executable_path="$1"
rootfs_dir="$2"

if [ ! -x "$executable_path" ]; then
  echo "Error: Executable '$executable_path' not found or not executable."
  exit 1
fi

if [ ! -d "$rootfs_dir" ]; then
  echo "Error: Rootfs directory '$rootfs_dir' not found."
  exit 1
fi

libs=$(ldd "$executable_path" | grep "=>" | awk '{print $3}' | sort -u)

echo "Copying dynamic loader and shared libraries for $executable_path..."

loader=$(readelf -l "$executable_path" | grep 'Requesting program interpreter' | awk -F': ' '{print $2}' | tr -d ']')
loader_path="/lib64/$(basename "$loader")"

if [ ! -f "$rootfs_dir/$loader_path" ]; then
  echo "Copying loader $loader ($(numfmt --to=iec --suffix=B "$(stat -c%s "$loader")"))"
  mkdir -p "$(dirname "$rootfs_dir/$loader_path")"
  cp "$loader" "$rootfs_dir/$loader_path"
fi

for lib in $libs; do
  dest="$rootfs_dir$lib"
  if [ ! -f "$dest" ]; then
    echo "Copying $lib ($(numfmt --to=iec --suffix=B "$(stat -c%s "$lib")"))"
    mkdir -p "$(dirname "$dest")"
    cp "$lib" "$dest"
  fi
done

echo "Done."
