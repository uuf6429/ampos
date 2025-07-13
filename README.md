# AMPOS

**A** **M**inimal **P**HP **O**perating **S**ystem

## Why?

This project came out of curiosity and a desire of "doing things differently".
PHP has gone a long way and is nowadays extremely stable and fast (maybe even compared to some of its competitors),
but it's probably far from a good choice for building an operating system.

Or at least, that's the theory. The aim of this project is to test that out (and learn a thing or two on the way).

## Architecture

So far it looks like so:

- grub boot loader
- minimal linux kernel
- busybox standalone shell (`/bin/sh`) - might be removed at some point
- PHP 8.4 (which takes over the init process)

## Requirements

The following tools are need to build and run the system:

- **Docker** (and **Docker Compose**) - The entire build process is done within docker this ensures that builds are
  consistent, reproducible and (individual steps) cacheable
- **PHP 8.4** and **Composer** - Needed at development-type. E.g. "tasks" are defined as composer scripts (instead of
  e.g. makefiles)
- **VirtualBox** - for running the built iso file in a VM. The startup process creates and runs a VM for you.

## Tasks

As mentioned, the various project tasks are defined as Composer scripts - check `composer.json` for available tasks.
E.g. to clean, build and run the project:

```shell
composer run clean-start
```
