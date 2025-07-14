jQuery(document).ready(function($) {
 
    var isLoading = false;

    function productsAjax( endCursor, templateSearch, collection, actionValue ) {

        var progress = 0;
        var progressInterval = setInterval(function() {
            progress += 10;
            if (progress >= 100) {
                clearInterval(progressInterval);
            }
        }, 300);

        $.ajax({
            url: wordclever_pagination_object.ajaxurl,
            type: 'POST',
            data: {
                action: 'wordclever_get_filtered_products',
                cursor: endCursor,
                search: templateSearch,
                collection: collection,
                wordclever_pagination_nonce: wordclever_pagination_object.nonce
            },
            success: function (response) {

                clearInterval(progressInterval);
                // jQuery('.wordclever-loader').hide();
                // jQuery('.wordclever-loader-overlay').hide();

                if (response.content) {

                    jQuery('.wordclever-load-more').show();

                    isLoading = false;

                    if ( actionValue != 'load' ) {
                        jQuery('.wordclever-templates-grid.wordclever-main-grid').empty();
                    }
                    jQuery('.wordclever-templates-grid.wordclever-main-grid').append(response.content);

                    const hasNextPage = response?.pagination?.hasNextPage;
                    const endCursor = response?.pagination?.endCursor;

                    jQuery('[name="wordclever-end-cursor"]').val(endCursor);
                    if (!hasNextPage) {
                        jQuery('[name="wordclever-end-cursor"]').val('');
                        jQuery('.wordclever-load-more').hide();
                        isLoading = true
                    }
                }
            },
            error: function () {
                
                clearInterval(progressInterval);
                // jQuery('.wordclever-loader').hide();
                // jQuery('.wordclever-loader-overlay').hide();

                console.log('Error loading products');
            }
        });
    }

    function debounce(func, delay) {
        let timeoutId;
        return function() {
            const context = this;
            const args = arguments;
            clearTimeout(timeoutId);
            timeoutId = setTimeout(() => {
                func.apply(context, args);
            }, delay);
        };
    }

    // License key verification
    $('#wordclever-license-form').on('submit', function(e) {
        e.preventDefault();
        
        const licenseKey = $('#wordclever-license-key').val();
        const $licenseKeyInput = $('#wordclever-license-key')
        const $submitButton = $('#wordclever-activate-license');
        const $statusSpan = $('.wordclever-license-status');
        
        if (!licenseKey) {
            Swal.fire({
                title: 'Error!',
                text: 'Please enter a license key',
                icon: 'error',
                confirmButtonText: 'OK'
            });
            return;
        }

        $submitButton.prop('disabled', true).text('Activating...');

        $.ajax({
            url: wordclever_pagination_object.ajaxurl,
            type: 'POST',
            data: {
                action: 'wordclever_verify_license',
                security: wordclever_pagination_object.dashboard_nonce,
                license_key: licenseKey
            },
            success: function(response) {
                if (response.success) {
                    $statusSpan.text('License Activated').addClass('wordclever-license-active').removeClass('wordclever-license-no-activation-status');
                    $submitButton.prop('disabled', true).text('Activated');
                    $licenseKeyInput.prop('disabled', true);
                    
                    Swal.fire({
                        title: 'Success!',
                        text: response.data.message,
                        icon: 'success',
                        confirmButtonText: 'OK'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            location.reload();
                        }
                    });
                } else {
                    $statusSpan.text('No Active License').removeClass('wordclever-license-active').addClass('wordclever-license-no-activation-status');
                    $submitButton.prop('disabled', false).text('Activate');
                    $licenseKeyInput.prop('disabled', false);
                    
                    Swal.fire({
                        title: 'Error!',
                        text: response.data.message,
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            },
            error: function() {
                $submitButton.prop('disabled', false).text('Activate');
                $licenseKeyInput.prop('disabled', false);
                Swal.fire({
                    title: 'Error!',
                    text: 'An error occurred while verifying the license. Please try again.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            }
        });
    });

    jQuery('.wordclever-templates-collections-group li').on('click', function() {

        // jQuery('.wordclever-loader').show();
        // jQuery('.wordclever-loader-overlay').show();

        let category = '';
        if (jQuery(this).hasClass('active')) {
            jQuery(this).removeClass('active');
        } else {
            jQuery('.wordclever-templates-collections-group li').removeClass('active');
            jQuery(this).addClass('active');
            
            category = jQuery(this).attr('data-value');
        }

        jQuery('.wordclever-templates-collections-group').removeClass('active');

        productsAjax( '', '', category, 'category' );
    });

    $('body').on("input", '[name="wordclever-templates-search"]', debounce(function (event) {

        const templateSearch = $('[name="wordclever-templates-search"]').val();

        $('#templates-tab').trigger('click');

        // jQuery('.wordclever-loader').show();
        // jQuery('.wordclever-loader-overlay').show();
        
        productsAjax( '', templateSearch, '', 'search' );
        
    }, 1000));

    $('body').on("click", '.wordclever-load-more', function (event) {
        event.preventDefault();

        isLoading = true;
        const endCursor = jQuery('[name="wordclever-end-cursor"]').val();
        const templateSearch = jQuery('[name="wordclever-templates-search"]').val();

        let collection = '';
        if (jQuery('.wordclever-templates-collections-group li.active')) {            
            collection = jQuery('.wordclever-templates-collections-group li.active').attr('data-value');
        }

        productsAjax( endCursor, templateSearch, collection, 'load' );
    });

    $('body').on("click", '.wordclever-filter-category-select', function (event) {
        $('.wordclever-templates-collections-group').toggleClass('active');
    });

    $('body').on("click", ".wordclever-dash-btn.forgot-pass", function (event) {
        event.preventDefault();
    
        $('.login-user-nav').hide();
        $('.reset-pass-nav').removeClass('d-none');
    
        $('#reset-pass-tab').addClass('active').click();
    
        $('#reset-pass').removeClass('d-none').addClass('show active');
        $('#login-user').removeClass('show active').addClass('d-none');
    });
    
    $('body').on("click", ".back-to-login", function (event) {
        event.preventDefault();
    
        $('.reset-pass-nav').addClass('d-none');
        $('#reset-pass').addClass('d-none').removeClass('show active');
    
        $('.login-user-nav').show();
        $('#login-user-tab').addClass('active').click();
    
        $('#login-user').removeClass('d-none').addClass('show active');
        $('#signup-user').addClass('d-none').removeClass('show active');
    });
    
    $('#login-registration button[data-bs-toggle="tab"]').on('click', function () {
        $('#login-registration button[data-bs-toggle="tab"]').removeClass('active');
        $(this).addClass('active');
        const content_id = $(this).attr('data-bs-target');        

        $('#loginRegistrationContent ' + content_id).removeClass('d-none');
    });

    $('body').on("submit", '#wordclever-reset-form', function (event) {
        event.preventDefault();

        const email_id = jQuery('#reset-pass-email').val();
        const newpassword = jQuery('#reset-new-pass').val();
        const confirmpassword = jQuery('#reset-confirm-pass').val();
        const isPasswordStep = !$('.new-pass-group').hasClass('d-none');
        const verification_status = isPasswordStep ? 'reset' : 'verify';     

        if (newpassword !== confirmpassword && verification_status === 'reset') {
            Swal.fire({
                title: 'Error!',
                text: 'Passwords do not match!',
                icon: "error",
                confirmButtonText: 'Retry'
            });
            return;
        }

        $.ajax({
            url: wordclever_pagination_object.ajaxurl,
            type: 'POST',
            data: {
                action: 'wordclever_reset_auth_pass',
                email_id: email_id,
                newpassword: newpassword,
                verification_status: verification_status,
                security: wordclever_pagination_object.reset_pass_nonce
            },
            success: function (response) {

                if (!response.success) {
                    Swal.fire({
                        title: 'Error!',
                        text: response?.data?.message || "Something went wrong!",
                        icon: "error",
                        confirmButtonText: 'Retry'
                    });
                } else {

                    Swal.fire({
                        title: 'Successful!',
                        text: isPasswordStep
                            ? 'Password reset successfully. Try logging in now.'
                            : 'Verification successful. Please enter a new password.',
                        icon: "success",
                        confirmButtonText: 'Done'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            if (isPasswordStep) {
                                $('.reset-pass-nav').addClass('d-none');
                                $('#reset-pass').addClass('d-none').removeClass('show active');
                            
                                $('.login-user-nav').show();
                                $('#login-user-tab').addClass('active').click();
                            
                                $('#login-user').removeClass('d-none').addClass('show active');
                                $('#signup-user').addClass('d-none').removeClass('show active');

                                $('#wordclever-reset-form')[0].reset();
                            } else {
                                $('.new-pass-group').removeClass('d-none');
                            }
                        }
                    });
                }

            },error: function (error) {
                Swal.fire({
                    title: 'Error!',
                    text: 'Something went wrong!',
                    icon: "error",
                    confirmButtonText: 'Retry'
                });
            }
        });
    });

    $('body').on("submit", '#wordclever-login-form', function (event) {
        event.preventDefault();

        const username = jQuery('#login-username').val();
        const password = jQuery('#login-pass').val();

        $.ajax({
            url: wordclever_pagination_object.ajaxurl,
            type: 'POST',
            data: {
                action: 'wordclever_auth',
                username: username,
                password: password,
                auth_action: 'login',
                security: wordclever_pagination_object.dashboard_nonce,
                site_url: wordclever_pagination_object.site_url
            },
            success: function (response) {

                if (!response.success) {
                    Swal.fire({
                        title: 'Error!',
                        text: response?.data?.message,
                        icon: "error",
                        confirmButtonText: 'Retry'
                    });
                } else {
                    Swal.fire({
                        title: 'Successful!',
                        text: 'Logged in successfully.',
                        icon: "success",
                        confirmButtonText: 'Done'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            location.reload();
                        }
                    });
                }
                
            },error: function (error) {
                Swal.fire({
                    title: 'Error!',
                    text: 'Something went wrong!',
                    icon: "error",
                    confirmButtonText: 'Retry'
                });
            }
        });
        
    });

    $('body').on("submit", '#wordclever-registration-form', function (event) {
        event.preventDefault();

        const username = jQuery('#signup-username').val();
        const email_id = jQuery('#signup-email').val();
        const password = jQuery('#signup-pass').val();

        if (password.length < 6) {
            Swal.fire({
                title: 'Error!',
                text: 'Password must be at least 6 characters long.',
                icon: 'error',
                confirmButtonText: 'OK'
             });
            return;
        }

        $.ajax({
            url: wordclever_pagination_object.ajaxurl,
            type: 'POST',
            data: {
                action: 'wordclever_auth',
                username: username,
                password: password,
                email: email_id,
                auth_action: 'signup',
                security: wordclever_pagination_object.dashboard_nonce,
                site_url: wordclever_pagination_object.site_url
            },
            success: function (response) {

                if (!response.success) {
                    Swal.fire({
                        title: 'Error!',
                        text: response?.data?.message,
                        icon: "error",
                        confirmButtonText: 'Retry'
                    });
                } else {
                    Swal.fire({
                        title: 'Successful!',
                        text: 'Registered successfully! Try logging in now.',
                        icon: "success",
                        confirmButtonText: 'Done'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $('#login-user-tab').click();
                        }
                    });
                }

            },error: function (error) {
                Swal.fire({
                    title: 'Error!',
                    text: 'Something went wrong!',
                    icon: "error",
                    confirmButtonText: 'Retry'
                });
            }
        });
    });

    $('body').on("submit", '#wordclever-support-form', function (event) {
        event.preventDefault();

        $.ajax({
            url: wordclever_pagination_object.ajaxurl,
            type: 'POST',
            data: {
                action: 'wordclever_support_tab_form',
                form_data: $(this).serialize(),
                security: wordclever_pagination_object.support_tab_nonce,
                site_url: wordclever_pagination_object.site_url
            },
            success: function (response) {                
                if (response.success) {

                    $('#wordclever-support-form')[0].reset();

                    Swal.fire({
                        title: 'Success!',
                        text: response.data.message,
                        icon: 'success',
                        confirmButtonText: 'OK'
                    });
                } else {
                    Swal.fire({
                        title: 'Error!',
                        text: response.data.message,
                        icon: 'error',
                        confirmButtonText: 'Retry'
                    });
                }
            },error: function (error) {
                Swal.fire({
                    title: 'Error!',
                    text: 'Something went wrong! Please try again later.',
                    icon: 'error',
                    confirmButtonText: 'Retry'
                });    
            }
        });
    });

    const countdownEl = $(".wordclever-bundle-banner-wrap .countdown");
    const startTime = localStorage.getItem("wordcleverCountdownStart") || Date.now();
    localStorage.setItem("wordcleverCountdownStart", startTime);
    
    function updateCountdown() {
        const now = Date.now();
        const elapsedTime = now - startTime;
        const totalDuration = 30 * 24 * 60 * 60 * 1000;
        
        let remainingTime = totalDuration - (elapsedTime % totalDuration);
        if (remainingTime <= 0) {
            localStorage.setItem("wordcleverCountdownStart", Date.now());
            remainingTime = totalDuration;
        }
        
        const days = Math.floor(remainingTime / (24 * 60 * 60 * 1000));
        const hours = Math.floor((remainingTime % (24 * 60 * 60 * 1000)) / (60 * 60 * 1000));
        const minutes = Math.floor((remainingTime % (60 * 60 * 1000)) / (60 * 1000));
        const seconds = Math.floor((remainingTime % (60 * 1000)) / 1000);
        
        countdownEl.html(`
            <span class="numbers">${days}<br><br><span class="nofont">Days</span></span> <span class="wordclever-colon-box">:</span> 
            <span class="numbers">${hours}<br><br><span class="nofont">Hours</span></span> <span class="wordclever-colon-box">:</span>
            <span class="numbers">${minutes}<br><br><span class="nofont">Mins</span></span> <span class="wordclever-colon-box">:</span> 
            <span class="numbers">${seconds}<br><br><span class="nofont">Secs</span></span> 
        `);
    }
    
    setInterval(updateCountdown, 1000);
    updateCountdown();
});