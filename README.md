# How to use

Create a WP_Query yet without params to not load the query, or simply use an existing wp_query
```php
$query = new WP_Query();
```
Then, set the query vars like usual
```php
$args = [
    'posts_per_page' => 10,
    'paged' => 1,
    'post_type' => 'any'
    // some other args if needed
];
foreach ($args as $arg => $value) {
    $query->set($arg, $value);
}
```
Generating facet from a query is as simple as this
```php
$facet = new QueryFacet();
$facet->setQuery($query);

// get all the possible post types and counts
$facet->addColumnFacet('post_type');
```
We can also generate a facet from a post meta or taxonomy 
```php
if ($customFilter = false) {
    // for example we want to filter by rating=3
    // but also find all the possible rating values when calling getFacets()
    $facet->addMetaFacet('rating', '3');
} else {
    // or maybe we want to apply a more custom filter (as we could also do with columns and taxonomies)
    // here we want posts that have a rating >= 3 while finding all the possible rating values
    $facet->addMetaFacet('rating', null, function (\WP_Query $query) {
        $metaQuery = $query->get('meta_query');
        if (!is_array($metaQuery)) {
            $metaQuery = ['relation' => 'AND'];
        }
        $metaQuery[] = [
            'key' => 'rating',
            'compare' => '>=',
            'value' => '3'
        ];
        $query->set('meta_query', $metaQuery);
    });
}
$facet->addTaxonomyFacet('category');
// and more facets if you need
```
Finally, just get the facets
```php
$facets = $facet->getFacets();
// $facets is an array with all the possible values & count
```