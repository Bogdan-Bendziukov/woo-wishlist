/**
 * Wishlist JS
 */
"use strict";

jQuery(document).ready(function ($) {
    let $wishlistBtn = $(".woo-wishlist-button");

    $wishlistBtn.on("click", function (e) {
        e.preventDefault();

        let btn = $(this);
        let icon = btn.find(".woo-wishlist-icon");
        let productID = btn.data("product-id");

        $.ajax({
            url: woo_wishlist.ajax_url,
            type: "POST",
            data: {
                action: "woo_wishlist_add_to_wishlist",
                product_id: productID,
                wishlist_nonce: woo_wishlist.wishlist_nonce,
            },
            beforeSend: function () {
                btn.addClass("loading");
                icon.removeClass("fa-heart");
                icon.addClass("fa-spinner");
            },
            success: function (response) {
                btn.removeClass("loading");
                icon.addClass("fa-heart");
                icon.removeClass("fa-spinner");

                console.log(response);

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

                    if (response.data.fragments) {
                        $.each(response.data.fragments, function (key, value) {
                            $(key).replaceWith(value);
                        });
                        $( document.body ).trigger( 'woo_wishlist_fragments_loaded' );
                    }
                } else {
                    alert(response.data.message);
                }
                
            },
        });
    });
});
