# Schema

A page with only a title is easy to treat as a generic article. A place, a product, a job, and an event need to say what they are. Schema Markup tells a search engine what a page is. The same markup is what an answer engine can read when it fetches the page. Quinrel prints it on this site. It does not send the schema to the Quinrel API. Open Schema Markup in Quinrel, or set the type on the post. Structured data does not guarantee a rich result, and it does not place the page in an AI answer.

## Types you can set

Article, NewsArticle, BlogPosting, WebPage, BreadcrumbList, FAQ, HowTo, LocalBusiness, Recipe, Event, Product, VideoObject, Person, Organization, PodcastEpisode, Course, JobPosting, Book, SoftwareApplication, Restaurant, Service, Review, Movie, MusicRecording, and Dataset.

You can also add one custom JSON-LD node. Breadcrumb Schema creates nested BreadcrumbList structured data from the post hierarchy and taxonomy terms.

When WooCommerce is active, product schema can include an aggregate rating if that product has reviews.

## Import from another page

You can import JSON-LD from a public page. Quinrel does not fetch a private or local address, and it does not run that page's JavaScript. Reading schema that appears only after JavaScript runs is coming soon.

Use the imported markup as a starting point for your own page. Importing it does not compare your site with another site, and it does not create a score.
