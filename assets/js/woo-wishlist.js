/**
 * Wishlist JS
 */
"use strict";

jQuery(document).ready(function ($) {
    let $wishlistBtn = $(".woo-wishlist-button");
    let $addAllToCartBtn = $(".woo-wishlist-add-all-to-cart-button");

    // Update variation ID on variation selection
    $('.single_variation_wrap').on('show_variation', function (event, variation) {
        let variationID = variation.variation_id.toString();
        let $btn = $('.single_variation_wrap .woo-wishlist-button');
        let variationsInWishlist = $btn.attr('data-variations-in-wishlist').toString().split(',');

        $btn.attr('data-variation-id', variationID);
        $btn.removeClass('variation-selection-needed');

        if (variationsInWishlist) {
            if (variationsInWishlist.includes(variationID)) {
                $btn.addClass('added');
                $btn.attr('aria-label', woo_wishlist.remove_from_wishlist_message);
                $btn.attr('title', woo_wishlist.remove_from_wishlist_message);
            } else {
                $btn.removeClass('added');
                $btn.attr('aria-label', woo_wishlist.add_to_wishlist_message);
                $btn.attr('title', woo_wishlist.add_to_wishlist_message);
            }
        }
    });

    // Reset variation ID if variation is not selected
    $('.variations_form').on('woocommerce_variation_select_change', function (event) {
        let $btn = $(event.target).find('.woo-wishlist-button');

        $btn.attr('data-variation-id', 0);
        $btn.addClass('variation-selection-needed');
        $btn.removeClass('added');
        $btn.attr('aria-label', woo_wishlist.add_to_wishlist_message);
        $btn.attr('title', woo_wishlist.add_to_wishlist_message);
    });

    $wishlistBtn.on("click", function (e) {
        e.preventDefault();

        let btn = $(this);
        let icon = btn.find(".woo-wishlist-icon");
        let productID = parseInt(btn.attr("data-product-id"));
        let variationID = parseInt(btn.attr("data-variation-id"));
        let hasVariation = typeof variationID !== 'undefined' && !isNaN(variationID) ? true : false;
        let data = {
            action: "woo_wishlist_add_to_wishlist",
            product_id: productID,
            wishlist_nonce: woo_wishlist.wishlist_nonce,
        };

        if (hasVariation) {
            if (variationID) {
                data.variation_id = variationID;
            } else {
                alert(woo_wishlist.variation_required_message);
                return;
            }
        }

        $.ajax({
            url: woo_wishlist.ajax_url,
            type: "POST",
            data: data,
            beforeSend: function () {
                btn.addClass("loading");
                icon.removeClass("fa-heart");
                icon.addClass("fa-spinner");
                
                //console.log(data);
            },
            success: function (response) {
                btn.removeClass("loading");
                icon.addClass("fa-heart");
                icon.removeClass("fa-spinner");

                //console.log(response);

                if (response.success) {
                    if (response.data.action === "added" || response.data.action === "updated") {
                        btn.addClass("added");
                    }
                    if (response.data.action === "removed") {
                        btn.removeClass("added");
                    }

                    btn.attr(
                        "aria-label",
                        response.data.btn_title
                    );
                    btn.attr("title", response.data.btn_title);

                    if (response.data.variations_in_wishlist) {
                        btn.attr(
                            "data-variations-in-wishlist",
                            response.data.variations_in_wishlist
                        );
                    }

                    if (response.data.fragments) {
                        $.each(response.data.fragments, function (key, value) {
                            $(key).replaceWith(value);
                        });
                        $(document.body).trigger('woo_wishlist_fragments_loaded');
                    }
                } else {
                    if (response.data.message) {
                        alert(response.data.message);
                    }
                }

            },
        });
    });

    $addAllToCartBtn.on("click", function (e) {
        const products = $(this).data('products');
        let data = {
            action: "woo_wishlist_add_all_to_cart",
            products: products,
            wishlist_nonce: woo_wishlist.wishlist_nonce,
        };

        $.ajax({
            url: woo_wishlist.ajax_url,
            type: "POST",
            data: data,
            beforeSend: function () {
                $addAllToCartBtn.addClass("loading");
            },
            success: function (response) {
                $addAllToCartBtn.removeClass("loading");

                if (response.success) {
                    console.log(response.data);
                    if (response.data.fragments) {
                        $.each(response.data.fragments, function (key, value) {
                            $(key).replaceWith(value);
                        });
                        $(document.body).trigger('woo_wishlist_fragments_loaded');
                    }
                } else {
                    if (response.data.message) {
                        alert(response.data.message);
                    }
                }

            },
        });
    });

});
