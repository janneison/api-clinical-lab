FROM mcr.microsoft.com/devcontainers/php:8.2

RUN apt-get update \
    && export DEBIAN_FRONTEND=noninteractive \
    && apt-get install -y --no-install-recommends \
        python3 \
        python3-pip \
    && pip3 install --no-cache-dir pre-commit \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*
