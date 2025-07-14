<?php
function wordclever_get_collections() {
    
    $endpoint_url = WORDCLEVER_ENDPOINT . 'getCollections';

    $options = [
        'body' => [],
        'headers' => [
            'Content-Type' => 'application/json'
        ]
    ];
    $response = wp_remote_post($endpoint_url, $options);

    if (!is_wp_error($response)) {
        $response_body = wp_remote_retrieve_body($response);
        $response_body = json_decode($response_body);

        if (isset($response_body->data) && !empty($response_body->data)) {
           return  $response_body->data;
        }
        return  [];
    }

    return  [];
}

function wordclever_get_premium_plans() {

    $endpoint_url = WORDCLEVER_ENDPOINT . 'get_open_ai_premium_plans';

    $options = [
        'body' => [],
        'headers' => [
            'Content-Type' => 'application/json'
        ]
    ];
    $response = wp_remote_post($endpoint_url, $options);

    if (!is_wp_error($response)) {
        $response_body = wp_remote_retrieve_body($response);
        $response_body = json_decode($response_body);

        if (isset($response_body->allOpenAiPlans) && !empty($response_body->allOpenAiPlans)) {
            return  $response_body->allOpenAiPlans;
        }
        return  [];
    }
    return  [];
}

function wordclever_get_filtered_products($cursor = '', $search = '', $collection = 'premium') {
    $endpoint_url = WORDCLEVER_ENDPOINT . 'getFilteredProducts';

    $remote_post_data = array(
        'collectionHandle' => $collection,
        'productHandle' => $search,
        'paginationParams' => array(
            "first" => 12,
            "afterCursor" => $cursor,
            "beforeCursor" => "",
            "reverse" => true
        )
    );

    $body = wp_json_encode($remote_post_data);

    $options = [
        'body' => $body,
        'headers' => [
            'Content-Type' => 'application/json'
        ]
    ];
    $response = wp_remote_post($endpoint_url, $options);

    if (!is_wp_error($response)) {
        $response_body = wp_remote_retrieve_body($response);
        $response_body = json_decode($response_body);

        if (isset($response_body->data) && !empty($response_body->data)) {
            if (isset($response_body->data->products) && !empty($response_body->data->products)) {
                return  array(
                    'products' => $response_body->data->products,
                    'pagination' => $response_body->data->pageInfo
                );
            }
        }
        return [];
    }
    
    return [];
}

function wordclever_get_filtered_products_ajax() {
    $cursor = isset($_POST['cursor']) ? sanitize_text_field(wp_unslash($_POST['cursor'])) : '';
    $search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
    $collection = isset($_POST['collection']) ? sanitize_text_field(wp_unslash($_POST['collection'])) : 'premium';

    check_ajax_referer('wordclever_create_pagination_nonce_action', 'wordclever_pagination_nonce');

    $get_filtered_products = wordclever_get_filtered_products($cursor, $search, $collection);
    ob_start();
    if (isset($get_filtered_products['products']) && !empty($get_filtered_products['products'])) {
        foreach ( $get_filtered_products['products'] as $product ) {

            $product_obj = $product->node;

            if (isset($product_obj->inCollection) && !$product_obj->inCollection) {
                continue;
            }
            
            $demo_url = '';$documentation_url = '';
            if (isset($product_obj->metafields->edges)) {
                foreach ($product_obj->metafields->edges as $metafield_edge) {
                    $metafield = $metafield_edge->node;
                    if ($metafield->key === 'custom.live_preview') {
                        $demo_url = $metafield->value;
                    } elseif ($metafield->key === 'custom.button_documentation') {
                        $documentation_url = $metafield->value;
                    }
                }
            }

            $product_url = isset($product->node->onlineStoreUrl) ? $product->node->onlineStoreUrl : '';
            $image_src = isset($product->node->images->edges[0]->node->src) ? $product->node->images->edges[0]->node->src : '';
            $product_price = isset($product->node->variants->edges[0]->node->price) ? $product->node->variants->edges[0]->node->price : ''; ?>

            <div class="wordclever-grid-item">
                <div class="wordclever-image-wrap">
                    <div class="wordclever-image-box">
                        <img src="<?php echo esc_url($image_src); ?>" alt="<?php echo esc_attr($product_obj->title); ?>" loading="lazy">
                    </div>
                    <div class="wordclever-image-overlay">
                        <?php if( $demo_url != '' ) { ?>
                            <a class="wordclever-demo-url wordclever-btn" href="<?php echo esc_url($demo_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html('Live Preview'); ?></a>
                        <?php } else { ?>
                            <a class="wordclever-demo-url wordclever-btn" href="<?php echo esc_url( WORDCLEVER_MAIN_URL . '/collections/gutenberg-wordpress-themes' ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html('Live Preview'); ?></a>
                        <?php } ?>
                        <footer>
                            <h3><?php echo esc_html($product_obj->title); ?></h3>
                        </footer>
                        <div class="d-flex" style="justify-content: space-between;">
                            <div class="wordclever-price-box">Price:
                                <div class="wordclever-price"><?php echo esc_html('$' . $product_price); ?></div>
                            </div>
                            <a class="wordclever-buy-now wordclever-btn" href="<?php echo esc_attr($product_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html('Buy It Now'); ?></a>
                        </div>
                    </div>
                </div>
            </div>
        <?php }
    }
    $output = ob_get_clean();

    $pagination = isset($get_filtered_products['pagination']) ?  $get_filtered_products['pagination'] : [];
    wp_send_json(array(
        'content' => $output,
        'pagination' => $pagination
    ));
}

add_action('wp_ajax_wordclever_get_filtered_products', 'wordclever_get_filtered_products_ajax');
add_action('wp_ajax_nopriv_wordclever_get_filtered_products', 'wordclever_get_filtered_products_ajax');