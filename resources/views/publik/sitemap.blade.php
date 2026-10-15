{!! '<'.'?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach (['home', 'public.profile', 'public.directory', 'public.products', 'public.posts', 'public.contact'] as $name)
    <url><loc>{{ route($name) }}</loc></url>
@endforeach
@foreach ($organizations as $organization)
    <url><loc>{{ route('public.organization', $organization->slug) }}</loc><lastmod>{{ $organization->updated_at->toAtomString() }}</lastmod></url>
@endforeach
@foreach ($products as $product)
    <url><loc>{{ route('public.product', $product->slug) }}</loc><lastmod>{{ $product->updated_at->toAtomString() }}</lastmod></url>
@endforeach
@foreach ($posts as $post)
    <url><loc>{{ route('public.post', $post->slug) }}</loc><lastmod>{{ $post->updated_at->toAtomString() }}</lastmod></url>
@endforeach
</urlset>
