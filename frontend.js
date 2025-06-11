jQuery(document).ready(function($) {

    /**
     * ===================================================================
     * === تابع کمکی جدید برای اسکرول نرم به سمت یک عنصر ===
     * ===================================================================
     * @param {object} element - عنصری که می‌خواهیم صفحه به آن اسکرول شود.
     */
    function scrollToElement(element) {
        // ابتدا بررسی می‌کنیم که عنصر در صفحه وجود داشته باشد
        if (element.length) {
            $('html, body').animate({
                // اسکرول به موقعیت بالای عنصر، با کمی فاصله از بالای صفحه (30 پیکسل)
                scrollTop: element.offset().top - 30 
            }, 500); // سرعت انیمیشن: 500 میلی‌ثانیه
        }
    }


    // مرحله ۱ -> مرحله ۲: گرفتن محصولات
    $('#it-category-select').on('change', function() {
        const catId = $(this).val();
        if (!catId) {
            $('#it-step-2').slideUp();
            return;
        }

        const productSelect = $('#it-product-select');
        productSelect.html('<option value="">در حال بارگذاری محصولات...</option>');
        
        // نمایش مرحله ۲ و سپس اسکرول به آن
        $('#it-step-2').slideDown(function() {
            scrollToElement($(this));
        });

        $('#it-step-3, #it-step-4, #it-step-5').slideUp();

        $.ajax({
            // ... کد ایجکس بدون تغییر ...
            url: it_ajax_obj.ajax_url, type: 'POST',
            data: { action: 'get_products_by_category', nonce: it_ajax_obj.nonce, category_id: catId },
            success: function(response) {
                if (response.success) {
                    const products = response.data;
                    let options = '<option value="">یک محصول را انتخاب کنید...</option>';
                    products.forEach(function(product) {
                        options += `<option value="${product.id}" data-price="${product.price}" data-name="${product.name}" data-description="${product.short_description}">${product.name}</option>`;
                    });
                    productSelect.html(options);
                } else {
                    productSelect.html(`<option value="">${response.data}</option>`);
                }
            }
        });
    });

    // مرحله ۲ -> مرحله ۳: نمایش توضیحات و فیلد اینستاگرام
    $('#it-product-select').on('change', function() {
        const selectedOption = $(this).find('option:selected');
        const descriptionContainer = $('#it-product-description');

        if ($(this).val()) {
            const description = selectedOption.data('description');
            if (description && description.trim() !== '') {
                descriptionContainer.html(description).show();
            } else {
                descriptionContainer.html('').hide();
            }
            
            // نمایش مرحله ۳ و سپس اسکرول به آن
            $('#it-step-3').slideDown(function() {
                scrollToElement($(this));
            });

        } else {
            descriptionContainer.html('').hide();
            $('#it-step-3, #it-step-4, #it-step-5').slideUp();
        }
    });

    // مرحله ۳ -> مرحله ۴: پس از وارد کردن آیدی اینستاگرام
    $('input[name="instagram_id"]').on('input', function() {
        if ($(this).val().length > 1) {
            // نمایش مرحله ۴ و سپس اسکرول به آن
            $('#it-step-4').slideDown(function() {
                scrollToElement($(this));
            });
        } else {
            $('#it-step-4, #it-step-5').slideUp();
        }
    });

    // مرحله ۴ -> مرحله ۵: نمایش پیش‌فاکتور
    $('#it-show-invoice-btn').on('click', function() {
        const selectedOption = $('#it-product-select option:selected');
        const productName = selectedOption.data('name');
        const productPrice = parseFloat(selectedOption.data('price'));
        const instagramId = $('input[name="instagram_id"]').val();
        const quantity = parseInt($('input[name="quantity"]').val());
        const totalPrice = productPrice * quantity;

        if (!productName || !instagramId || !quantity || quantity < 1) {
            alert('لطفاً تمام فیلدها را به درستی کامل کنید.');
            return;
        }

        const invoiceHtml = `
            <p><strong>نام محصول:</strong> <span>${productName}</span></p>
            <p><strong>آیدی اینستاگرام:</strong> <span>${instagramId}</span></p>
            <p><strong>قیمت واحد:</strong> <span>${productPrice.toLocaleString('fa-IR')} تومان</span></p>
            <p><strong>تعداد:</strong> <span>${quantity}</span></p>
            <hr>
            <p><strong>مبلغ کل:</strong> <span>${totalPrice.toLocaleString('fa-IR')} تومان</span></p>
        `;
        
        $('#it-invoice-details').html(invoiceHtml);
        
        // نمایش مرحله ۵ و سپس اسکرول به آن
        $('#it-step-5').slideDown(function() {
            scrollToElement($(this));
        });
    });
});