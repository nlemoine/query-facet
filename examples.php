<?php 

// nobody can access this file, it's just examples
return;

// create a WP_Query yet without params to not load the query
$query = new WP_Query();

$args = [
    'posts_per_page' => 10,
    'paged' => 1,
    'post_type' => 'any'
    // some other args if needed
];

foreach ($args as $arg => $value) {
    $query->set($arg, $value);
}

$facet = new QueryFacet();
$facet->setQuery($query);

$facet->addColumnFacet('post_type');

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

$facets = $facet->getFacets();
// $facets is an array with all the possible values & count
