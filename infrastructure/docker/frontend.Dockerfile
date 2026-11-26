FROM node:22-alpine AS builder

WORKDIR /app
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci

COPY frontend/ ./
ARG NUXT_PUBLIC_API_BASE=/api/v1
ENV NUXT_PUBLIC_API_BASE=${NUXT_PUBLIC_API_BASE}
RUN npm run build

FROM node:22-alpine AS runner
WORKDIR /app
ENV NODE_ENV=production
ENV HOST=0.0.0.0
ENV PORT=3000

COPY --from=builder /app/.output ./.output

EXPOSE 3000
CMD ["node", ".output/server/index.mjs"]
