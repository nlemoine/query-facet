# What is Query Facet ?

Query Facet is a simple Wordpress plugin that use WP queries to generate some facets
This plugin is meant to be used by developers, it does really nothing by just installing it

# What we need

We need 2 things :

The first is the WP_Query on wich we need to work.
It should not be loaded at the time we use it, because the plugin needs to apply some filters on it

The second is the different facets we need to retrieve
Currently, we can generate a facet from a post meta, a post taxonomy or a post column

# How to use

Create a WP_Query yet without params to not load the query, or simply use an existing wp_query (again, not loaded)
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

/* $facets is an array with all the possible values & count
[
    'post_type' => 
    [
		0 => [
			'value' => 'post',
			'count' => 61
		],
		1 => [
			'value' => 'some_custom_post_type',
			'count' => 5
		]
    ]
	'rating' => 
    [
		0 => [
			'value' => '1',
			'count' => 2
		],
		1 => [
			'value' => '2',
			'count' => 5
		],
		2 => [
			'value' => '3',
			'count' => 8
		],
		3 => [
			'value' => '4',
			'count' => 4
		],
		4 => [
			'value' => '5',
			'count' => 2
		]
    ],
	'category' => 
    [
		0 => [
			'value' => 'some-category',
			'name' => 'Some category'
			'count' => 16
		],
		... 
    ]
]
*/
```