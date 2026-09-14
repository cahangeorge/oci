# ─── OCI Cookie Scanner ──────────────────────────────────
# Self-contained scanning server with:
#   - Website Evidence Collector (WEC) from EU EDPS
#   - Debian Chromium for native ARM64 support
#   - Node.js API server
#
# Deploy alongside the main app or on separate servers.
# Communicates via HTTP API with shared API key auth.
#
# Build:  docker build -t oci-scanner docker/scanner/
# Run:    docker run -p 8300:8300 -e SCANNER_API_KEY=secret oci-scanner
# ─────────────────────────────────────────────────────────

FROM node:20-slim

# Chromium + runtime libraries and fonts. Chrome-for-Testing does not publish a
# Linux ARM64 binary, so the scanner uses Debian's native Chromium package.
RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates \
    chromium \
    curl \
    fonts-liberation \
    fonts-noto-color-emoji \
    fonts-noto-cjk \
    libasound2 \
    libatk-bridge2.0-0 \
    libatk1.0-0 \
    libatspi2.0-0 \
    libcairo2 \
    libcups2 \
    libdbus-1-3 \
    libdrm2 \
    libgbm1 \
    libglib2.0-0 \
    libgtk-3-0 \
    libnspr4 \
    libnss3 \
    libpango-1.0-0 \
    libx11-6 \
    libx11-xcb1 \
    libxcb1 \
    libxcomposite1 \
    libxdamage1 \
    libxext6 \
    libxfixes3 \
    libxkbcommon0 \
    libxrandr2 \
    libxrender1 \
    libxshmfence1 \
    xdg-utils \
    && rm -rf /var/lib/apt/lists/*

# Point Puppeteer at the system Chromium (puppeteer-core never downloads one).
ENV PUPPETEER_SKIP_CHROMIUM_DOWNLOAD=true \
    PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium \
    CHROMIUM_PATH=/usr/bin/chromium

# Create app directory
WORKDIR /opt/scanner

# Copy package files and install dependencies
COPY package.json package-lock.json* ./
RUN npm ci --production 2>/dev/null || npm install --production

# Copy scanner application
COPY server.js ./
COPY lib/ ./lib/

# Create non-root user for security
RUN groupadd -r scanner && useradd -r -g scanner -G audio,video scanner \
    && mkdir -p /tmp/scans /home/scanner \
    && chown -R scanner:scanner /opt/scanner /tmp/scans /home/scanner

USER scanner

# Environment defaults (override at runtime)
ENV PORT=8300 \
    SCANNER_API_KEY="" \
    MAX_CONCURRENT=3 \
    SCAN_TIMEOUT=60000 \
    NODE_ENV=production

EXPOSE 8300

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD curl -f http://localhost:8300/health || exit 1

CMD ["node", "server.js"]
