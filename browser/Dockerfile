FROM mcr.microsoft.com/playwright:v1.56.1-noble
WORKDIR /runner
COPY package.json package-lock.json ./
RUN npm ci --omit=dev
COPY *.mjs ./
USER pwuser
CMD ["node", "server.mjs"]
