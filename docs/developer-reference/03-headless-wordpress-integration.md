# Headless WordPress Integration Guide

Quinrel provides dedicated, public headless REST endpoints designed to feed decoupled frontends (Next.js, Nuxt, Astro, Remix, SvelteKit) with complete metadata and JSON-LD structured data graphs.

---

## 1. The Headless REST Endpoint

Unlike default WordPress REST responses that scatter metadata across disparate fields, Quinrel provides a single consolidated headless route:

```http
GET /wp-json/quinrel/v1/headless/post/{id}
```

This endpoint requires zero authentication and delivers a fully computed SEO payload ready for client injection.

---

## 2. Next.js 14+ (App Router) Integration

In Next.js App Router, use the `generateMetadata` function and inject JSON-LD into your `page.tsx`:

```tsx
// app/blog/[slug]/page.tsx
import { Metadata, ResolvingMetadata } from 'next';
import { notFound } from 'next/navigation';

interface Props {
  params: { slug: string };
}

interface QuinrelHeadlessData {
  title: string;
  meta_description: string;
  canonical_url: string;
  robots: {
    index: boolean;
    follow: boolean;
    noai?: boolean;
    noimageai?: boolean;
    max_snippet?: number;
    max_image_preview?: string;
  };
  opengraph: {
    title: string;
    description: string;
    url: string;
    site_name: string;
    image: string;
    type: string;
  };
  twitter: {
    card: string;
    title: string;
    image: string;
  };
  schema: Record<string, unknown>;
}

// 1. Fetch Post & Quinrel SEO in parallel or combined
async function getPostData(slug: string) {
  const wpRes = await fetch(`https://cms.example.com/wp-json/wp/v2/posts?slug=${slug}`);
  const posts = await wpRes.json();
  if (!posts || posts.length === 0) return null;

  const post = posts[0];
  const seoRes = await fetch(`https://cms.example.com/wp-json/quinrel/v1/headless/post/${post.id}`, {
    next: { tags: [`post-${post.id}`, 'quinrel-seo'] },
  });
  const seo: QuinrelHeadlessData = await seoRes.json();

  return { post, seo };
}

// 2. Dynamic Metadata Generator
export async function generateMetadata(
  { params }: Props,
  parent: ResolvingMetadata
): Promise<Metadata> {
  const data = await getPostData(params.slug);
  if (!data) return {};

  const { seo } = data;

  return {
    title: seo.title,
    description: seo.meta_description,
    alternates: {
      canonical: seo.canonical_url,
    },
    robots: {
      index: seo.robots.index,
      follow: seo.robots.follow,
      nocache: false,
      googleBot: {
        index: seo.robots.index,
        follow: seo.robots.follow,
        'max-video-preview': -1,
        'max-image-preview': 'large',
        'max-snippet': -1,
      },
    },
    openGraph: {
      title: seo.opengraph.title,
      description: seo.opengraph.description,
      url: seo.opengraph.url,
      siteName: seo.opengraph.site_name,
      images: [{ url: seo.opengraph.image }],
      type: 'article',
    },
    twitter: {
      card: seo.twitter.card === 'summary_large_image' ? 'summary_large_image' : 'summary',
      title: seo.twitter.title,
      images: [seo.twitter.image],
    },
    other: {
      ...(seo.robots.noai && { 'robots': 'noai, noimageai' }),
    },
  };
}

// 3. Page Component with Injected JSON-LD Graph
export default async function BlogPostPage({ params }: Props) {
  const data = await getPostData(params.slug);
  if (!data) notFound();

  return (
    <article className="prose mx-auto py-12">
      {/* Safe injection of complete Quinrel Schema graph */}
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: JSON.stringify(data.seo.schema) }}
      />
      <h1>{data.post.title.rendered}</h1>
      <div dangerouslySetInnerHTML={{ __html: data.post.content.rendered }} />
    </article>
  );
}
```

---

## 3. Nuxt 3 (Vue) Integration

In Nuxt 3, utilize `useSeoMeta` and `useHead` inside your dynamic page template:

```vue
<!-- pages/blog/[slug].vue -->
<template>
  <main v-if="post">
    <Head>
      <!-- Safe Schema.org JSON-LD Output -->
      <component
        :is="'script'"
        type="application/ld+json"
        v-text="JSON.stringify(seo.schema)"
      />
    </Head>
    <article>
      <h1>{{ post.title.rendered }}</h1>
      <div v-html="post.content.rendered" />
    </article>
  </main>
</template>

<script setup lang="ts">
const route = useRoute();
const config = useRuntimeConfig();

// 1. Fetch Post from WordPress
const { data: posts } = await useFetch(
  `${config.public.wpUrl}/wp-json/wp/v2/posts?slug=${route.params.slug}`
);
const post = computed(() => posts.value?.[0]);

if (!post.value) {
  throw createError({ statusCode: 404, message: 'Article Not Found' });
}

// 2. Fetch Quinrel Headless SEO
const { data: seo } = await useFetch(
  `${config.public.wpUrl}/wp-json/quinrel/v1/headless/post/${post.value.id}`
);

// 3. Inject SEO Meta Tags
useSeoMeta({
  title: () => seo.value?.title,
  description: () => seo.value?.meta_description,
  ogTitle: () => seo.value?.opengraph?.title,
  ogDescription: () => seo.value?.opengraph?.description,
  ogImage: () => seo.value?.opengraph?.image,
  ogUrl: () => seo.value?.opengraph?.url,
  twitterCard: 'summary_large_image',
  robots: () => `${seo.value?.robots?.index ? 'index' : 'noindex'}, ${seo.value?.robots?.follow ? 'follow' : 'nofollow'}`,
});

if (seo.value?.robots?.noai) {
  useHead({
    meta: [
      { name: 'robots', content: 'noai, noimageai' },
    ],
  });
}
</script>
```

---

## 4. Astro Integration

In Astro components (`.astro`), query the endpoint inside frontmatter and render tags into the `<head>` slot:

```astro
---
// src/pages/blog/[slug].astro
const { slug } = Astro.params;

const wpRes = await fetch(`https://cms.example.com/wp-json/wp/v2/posts?slug=${slug}`);
const posts = await wpRes.json();
if (!posts || posts.length === 0) {
  return Astro.redirect('/404');
}

const post = posts[0];
const seoRes = await fetch(`https://cms.example.com/wp-json/quinrel/v1/headless/post/${post.id}`);
const seo = await seoRes.json();
---

<html lang="en">
  <head>
    <meta charset="utf-8" />
    <title>{seo.title}</title>
    <meta name="description" content={seo.meta_description} />
    <link rel="canonical" href={seo.canonical_url} />

    <!-- Robots & AI Controls -->
    <meta
      name="robots"
      content={`${seo.robots.index ? 'index' : 'noindex'}, ${seo.robots.follow ? 'follow' : 'nofollow'}${seo.robots.noai ? ', noai, noimageai' : ''}`}
    />

    <!-- OpenGraph -->
    <meta property="og:title" content={seo.opengraph.title} />
    <meta property="og:description" content={seo.opengraph.description} />
    <meta property="og:url" content={seo.opengraph.url} />
    <meta property="og:image" content={seo.opengraph.image} />
    <meta property="og:site_name" content={seo.opengraph.site_name} />

    <!-- Twitter -->
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content={seo.twitter.title} />
    <meta name="twitter:image" content={seo.twitter.image} />

    <!-- Schema Graph -->
    <script type="application/ld+json" set:html={JSON.stringify(seo.schema)} />
  </head>
  <body>
    <article>
      <h1>{post.title.rendered}</h1>
      <div set:html={post.content.rendered} />
    </article>
  </body>
</html>
```

---

## 5. Webhook Revalidation (Instant Cache Purge)

When an editor publishes an update in WordPress, you can trigger instant on-demand revalidation in Next.js or Nuxt:
* Quinrel emits standard `post_updated` and `save_post` actions.
* You can configure a simple outgoing webhook pointing to your frontend endpoint (e.g., `https://frontend.example.com/api/revalidate?secret=...&id=142`).

