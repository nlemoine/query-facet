<?php

/*
 * Plugin Name: Query Facet
 * Description: Generate facets from a WP_Query.
 * Author: AdFab
 * Author URI: https://adfab.fr
 * Version: 1.0.0
 */

/**
 * @see examples.php for some examples
 */
class QueryFacet
{
    const TYPE_TAXONOMY = 'taxonomy';
    const TYPE_META = 'meta';
    const TYPE_COLUMN = 'column';
    
    /**
     * the wp_query on wich we want to get the facets
     * 
     * @var \WP_Query
     */
    protected $query;
    
    /**
     * all the facets we want to get from the $query
     * 
     * @var array
     */
    protected $facets;
    
    /**
     * @var \wpdb
     */
    protected $wpdb;
    
    /**
     * @var array
     */
    protected $calculatedFacets;
    
    /**
     * used on filters to remember on wich current facet we need to work
     * 
     * @var string
     */
    protected $currentFacet;
    
    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->facets = [];
    }
    
    /**
     * Set the \WP_Query, already with filters, but not yet loaded
     *
     * @param \WP_Query $query
     */
    public function setQuery(\WP_Query $query)
    {
        $this->query = $query;
    }
    
    /**
     * Generate facets by executing clones of the $query with the right filters
     * 
     * @param bool $applyFilters wether to apply all the filters to the wp_query
     * @return array
     */
    public function getFacets(bool $applyFilters = true): array
    {
        if ($this->calculatedFacets) {
            return $this->calculatedFacets;
        }
        $this->calculatedFacets = [];
        
        add_filter('posts_request', [$this, 'postsRequest']);
        add_filter('posts_clauses', [$this, 'postsClauses']);
        
        foreach ($this->facets as $facet) {
            $query = clone $this->query;
            $query->set('no_found_rows', true);
            $this->currentFacet = $facet;
            // apply filters but the current
            foreach ($this->facets as $facet) {
                if ($facet['name'] === $this->currentFacet['name']) {
                    continue;
                }
                $this->filter($query, $facet);
            }
            $query->get_posts();
        }
        
        remove_filter('posts_clauses', [$this, 'postsClauses']);
        remove_filter('posts_request', [$this, 'postsRequest']);
        
        if ($applyFilters) {
            foreach ($this->facets as $facet) {
                $this->filter($this->query, $facet);
            }
        }
        
        return $this->calculatedFacets;
    }
    
    /**
     * @param \WP_Query $query
     * @param array $facet [type, name, value]
     */
    protected function filter(\WP_Query $query, array $facet)
    {
        if (is_callable($facet['callable'])) {
            $facet['callable']($query, $facet);
            return;
        }
        
        if (is_null($facet['value'])) {
            return;
        }
        
        if (SELF::TYPE_TAXONOMY === $facet['type']) {
            $taxQuery = $query->get('tax_query');
            if (!is_array($taxQuery)) {
                $taxQuery = ['relation' => 'AND'];
            }
            if (is_array($facet['value'])) {
                $taxQuery[] = $facet['value'];
            }
            $taxQuery[] = [
                'taxonomy' => $facet['name'],
                'field' => 'term_id',
                'terms' => $facet['value']
            ];
            $query->set('tax_query', $taxQuery);
            return;
        }
        if (SELF::TYPE_META === $facet['type']) {
            $metaQuery = $query->get('meta_query');
            if (!is_array($metaQuery)) {
                $metaQuery = ['relation' => 'AND'];
            }
            if (is_array($facet['value'])) {
                $metaQuery[] = $facet['value'];
            }
            $metaQuery[] = [
                'key' => $facet['name'],
                'compare' => '=',
                'value' => $facet['value']
            ];
            $query->set('meta_query', $metaQuery);
            return;
        }
        if (SELF::TYPE_COLUMN === $facet['type']) {
            $query->set($facet['name'], $facet['value']);
            return;
        }
        throw new \Exception('Unkown facet type: ' . $facet['type']);
    }
    
    /**
     * @param string $taxonomy
     * @param mixed $value
     *     the tax_id, or the tax_query array
     *     null means no filter
     * @param callable $callable
     *     make your own filter; if specified, $value is ignored
     */
    public function addTaxonomyFacet(string $taxonomy, $value = null, ?callable $callable = null)
    {
        $this->facets[$taxonomy] = [
            'type' => self::TYPE_TAXONOMY,
            'name' => $taxonomy,
            'value' => $value,
            'callable' => $callable
        ];
    }
    
    /**
     * @param string $meta from the wp_postmeta table
     * @param mixed $value
     *     the meta_value, or the meta_query array
     *     null means no filter
     * @param callable $callable
     *     make your own filter; if specified, $value is ignored
     */
    public function addMetaFacet(string $meta, $value = null, ?callable $callable = null)
    {
        $this->facets[$meta] = [
            'type' => self::TYPE_META,
            'name' => $meta,
            'value' => $value,
            'callable' => $callable
        ];
    }
    
    /**
     * @param string $column from the wp_posts table
     * @param mixed $value
     *     the meta_value, or the meta_query array
     *     null means no filter
     * @param callable $callable
     *     make your own filter; if specified, $value is ignored
     */
    public function addColumnFacet(string $column, $value = null, ?callable $callable = null)
    {
        $this->facets[$column] = [
            'type' => self::TYPE_COLUMN,
            'name' => $column,
            'value' => $value,
            'callable' => $callable
        ];
    }
    
    /**
     * Prevent the query to run
     * Get all the possible values for a facet instead
     *
     * @param string $request
     * @return string
     */
    public function postsRequest(string $request): string
    {
        $this->calculatedFacets[$this->currentFacet['name']] = $this->wpdb->get_results($request);
        return '';
    }
    
    /**
     * @param array $clauses
     * @return array
     */
    public function postsClauses(array $clauses): array
    {
        if (self::TYPE_TAXONOMY === $this->currentFacet['type']) {
            $clauses['join'] .= ' INNER JOIN '.$this->wpdb->term_relationships.' AS qf_tr ON qf_tr.object_id = '.$this->wpdb->posts.'.ID';
            $clauses['join'] .= ' INNER JOIN '.$this->wpdb->term_taxonomy.' AS qf_tt ON qf_tt.term_taxonomy_id = qf_tr.term_taxonomy_id';
            $clauses['join'] .= ' INNER JOIN '.$this->wpdb->terms.' AS qf_t ON qf_t.term_id = qf_tt.term_id';
            $clauses['where'] .= ' AND qf_tt.taxonomy = "'.esc_sql($this->currentFacet['name']).'"';
            $clauses['fields'] = 'qf_t.slug AS value, qf_t.name AS name, COUNT(DISTINCT wp_posts.ID) AS count';
            $clauses['groupby'] = 'qf_t.slug';
            $clauses['limits'] = '';
            $clauses['orderby'] = '';
            return $clauses;
        }
        if (self::TYPE_META === $this->currentFacet['type']) {
            $alias = 'qf_' . $this->currentFacet['type'];
            $clauses['join'] .= ' INNER JOIN '.$this->wpdb->postmeta.' AS '.$alias.' ON '.$alias.'.post_id = '.$this->wpdb->posts.'.ID AND '.$alias.'.meta_key = "'.esc_sql($this->currentFacet['name']).'"';
            $clauses['fields'] = $alias.'.meta_value AS value, COUNT(DISTINCT '.$this->wpdb->posts.'.ID) AS count';
            $clauses['groupby'] = $alias.'.meta_value';
            $clauses['limits'] = '';
            $clauses['orderby'] = '';
            return $clauses;
        }
        if (self::TYPE_COLUMN === $this->currentFacet['type']) {
            $alias = 'qf_' . $this->currentFacet['type'];
            $clauses['fields'] = $this->wpdb->posts.'.'.esc_sql($this->currentFacet['name']).' AS value, COUNT(DISTINCT '.$this->wpdb->posts.'.ID) AS count';
            $clauses['groupby'] = $this->wpdb->posts.'.'.esc_sql($this->currentFacet['name']);
            $clauses['limits'] = '';
            $clauses['orderby'] = '';
            return $clauses;
        }
        throw new \Exception('Unkown facet type: ' . $this->currentFacet['type']);
    }
}
