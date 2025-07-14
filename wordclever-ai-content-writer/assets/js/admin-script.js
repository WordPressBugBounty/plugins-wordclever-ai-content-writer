jQuery(document).ready(function ($) {
    $('#wordclever-generate-content').on('click', function () {
        const contentType = $('#wordclever-content-type').val();
        const keyword = $('#wordclever-keyword').val();
        const tone = $('#wordclever-tone').val();
        const numResults = $('#wordclever-num-results').val(); 
        const license_key = $('#wordclever-generate-license-key').val(); 

        if (!contentType || !keyword || !tone || !numResults) {
            Swal.fire({
                title: 'Error!',
                text: 'Please fill in all fields before generating content.',
                icon: 'error',
                confirmButtonText: 'OK'
             });
             return;
        }

        $('#wordclever-results').html('<p>Generating content...</p>');

        $.ajax({
            url: wordclever_data.ajax_url,
            method: 'POST',
            data: {
                action: 'wordclever_generate_content',
                security: wordclever_data.nonce,
                content_type: contentType,
                keyword: keyword,
                tone: tone,
                license_key: license_key,
                resp_count: numResults, 
            },
            success: function (response) {
                if (response.success) {
                    const results = response.data.results;
            
                    if (Array.isArray(results)) {
                        let output = '';
                        results.forEach((result, index) => {
                            // Replace ** with <strong> for bolding
                            let formattedResult = result.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
                            formattedResult = formattedResult.replace('Meta Title:', '<br>Meta Title:');
                            formattedResult = formattedResult.replace('Meta Description:', '</p><p>Meta Description:');
            
                            output += `<strong>Result ${index + 1}</strong>:<br>${formattedResult}<br><br>`;
                        });
                        $('#wordclever-results').html(output);
                        // used_request dynamically
                         updateUsedRequest();
                    } else {
                        let formattedResult = results.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
                        formattedResult = formattedResult.replace('Meta Title:', '<br>Meta Title:');
                        formattedResult = formattedResult.replace('Meta Description:', '</p><p>Meta Description:');
            
                        $('#wordclever-results').html(`Generated Content:<br><br>${formattedResult}`);
                    }
                } else {
                    $('#wordclever-results').html(
                        `<p style="color: red;">${response.data.message}</p>`
                    );
                }
            },
            error: function (xhr, status, error) {
                $('#wordclever-results').html(
                    `<p style="color: red;">An error occurred: ${error}</p>`
                );
            },
        });
    });

    //for used request
    function updateUsedRequest() {
        $.ajax({
            url: wordclever_data.ajax_url,
            method: 'POST',
            data: {
                action: 'wordclever_get_used_request',
                security: wordclever_data.nonce,
            },
            success: function (response) {
                if (response.success) {
                    $('#wordclever-request-info')
                        .find('strong')
                        .eq(1) // Second <strong> element
                        .text(response.data.used_request);
                }
            },
            error: function () {
                console.error('Failed to update used requests.');
            }
        });
    }
});
//end

//for popup

jQuery(document).ready(function($) {
    // Show the popup
    $('#wordclever-login-popup-trigger').on('click', function() {
        $('#wordclever-popup').removeClass('hidden');
        $('#wordclever-login-form').show();
        $('#wordclever-register-form').hide();
    });

    $('#wordclever-toggle-register').on('click', function() {
        $('#wordclever-login-form').hide();
        $('#wordclever-register-form').show();
    });

    $('#wordclever-toggle-login').on('click', function() {
        $('#wordclever-register-form').hide();
        $('#wordclever-login-form').show();
    });

    $('#wordclever-popup-close').on('click', function() {
        $('#wordclever-popup').addClass('hidden');
    });
});

// for login and signup 

jQuery(document).ready(function ($) {
 // for login
 $('#wordclever-login-submit').click(function (event) {
    event.preventDefault(); 

    const username = $('#wordclever-login-username').val();
    const password = $('#wordclever-login-password').val();
    //new add for sweet
    // Frontend validation using SweetAlert
    if (username === '' || password === '') {
        Swal.fire({
           title: 'Error!',
           text: 'All fields are required.',
           icon: 'error',
           confirmButtonText: 'OK'
        });
        return;
     }
     
     if (password.length < 6) {
        Swal.fire({
           title: 'Error!',
           text: 'Password must be at least 6 characters long.',
           icon: 'error',
           confirmButtonText: 'OK'
        });
        return;
     }

    //end for sweet

    $.ajax({
        url: wordclever_data.ajax_url,
        type: 'POST',
        data: {
            action: 'wordclever_auth',
            security: wordclever_data.nonce,
            username: username,
            password: password,
            auth_action: 'login',
            site_url: wordclever_data.site_url 
        },
        success: function (response) {
            console.log(response);

            try {
                if (typeof response === 'string') {
                    response = JSON.parse(response);
                }
                if (response.success) {
                    Swal.fire({
                        title: 'Done!',
                        text: 'Login successful!.',
                        icon: "success",
                        confirmButtonText: 'OK'
                     });
                    location.reload();

                } else {
                    const errorMessage = response.data?.message || 'An error occurred.';
                    Swal.fire({
                        title: 'Information',
                        text: 'Info: ' + errorMessage,
                        icon: 'info', 
                        confirmButtonText: 'OK'
                    });
                }
            } catch (e) {
                if (typeof response === 'string' && response.includes('User logged in')) {
                    Swal.fire({
                        title: 'Done!',
                        text: 'Login successful!.',
                        icon: "success",
                        confirmButtonText: 'OK'
                     });
                    location.reload();
                } else {
                    Swal.fire({
                        title: 'Error!',
                        text: 'Error: Unknown error occurred',
                        icon: "error",
                        confirmButtonText: 'OK'
                    });
                }
            }
        },
        error: function (xhr, status, error) {
            console.error('AJAX Error:', status, error);
            Swal.fire({
                title: 'Error!',
                text: 'An error occurred in login. Please try again.',
                icon: 'error',
                confirmButtonText: 'OK'
             });
        }
    });
});

// for register
$('#wordclever-register-submit').click(function (event) {
    event.preventDefault(); // Prevent form submission
    const username = $('#wordclever-register-username').val();
    const password = $('#wordclever-register-password').val();
    const email = $('#wordclever-register-email').val();

    // Frontend validation
    if (username === '' || email === '' || password === '') {
        Swal.fire({
            title: 'Error!',
            text: 'All fields are required.',
            icon: 'error',
            confirmButtonText: 'OK'
         });
        return;
    }

    if (!validateEmail(email)) {
        Swal.fire({
            title: 'Error!',
            text: 'Please enter a valid email address.',
            icon: 'error',
            confirmButtonText: 'OK'
         });
        return;
    }

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
        url: wordclever_data.ajax_url,
        type: 'POST',
        data: {
            action: 'wordclever_auth',
            security: wordclever_data.nonce,
            username: username,
            password: password,
            email: email,
            auth_action: 'signup',
            site_url: wordclever_data.site_url 
        },
        success: function (response) {
            console.log(response);
        
            // Handle nested success logic
            const data = response.data || {};
            if (data.success) {
                Swal.fire({
                    title: 'Success!',
                    text: data.message,
                    icon: 'success',  
                    confirmButtonText: 'OK'  
                });
            } else {
                const errorMessage = data.message || 'Registration failed. Please try again.';
                Swal.fire({
                    title: 'Information',
                    text: errorMessage,
                    icon: 'info',  
                    confirmButtonText: 'OK'  
                });
            }
        },
        
        error: function (xhr, status, error) {
            console.error('AJAX Error:', status, error);
            Swal.fire({
                title: 'Error!',
                text: 'An error occurred during registration. Please try again.',
                icon: 'error',
                confirmButtonText: 'OK'
             });
        }
    });
});

// Email
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}


});

//for pass icon

jQuery(document).ready(function ($) {
    $('#toggle-login-password').on('click', function () {
        const passwordField = $('#wordclever-login-password');
        const passwordType = passwordField.attr('type') === 'password' ? 'text' : 'password';
        passwordField.attr('type', passwordType);
        $(this).toggleClass('dashicons-visibility').toggleClass('dashicons-hidden');
    });
    $('#toggle-register-password').on('click', function () {
        const passwordField = $('#wordclever-register-password');
        const passwordType = passwordField.attr('type') === 'password' ? 'text' : 'password';
        passwordField.attr('type', passwordType);

        $(this).toggleClass('dashicons-visibility').toggleClass('dashicons-hidden');
    });
});


