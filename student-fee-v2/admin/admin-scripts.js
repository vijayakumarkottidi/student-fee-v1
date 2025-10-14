jQuery(function($){
    var currentSearch = '';

    // Load table and metrics
    function loadTable(searchVal) {
        currentSearch = searchVal || '';
        
        $('#sfm-table').html('<div class="sfm-loading">Loading student data...</div>');
        $('#sfm-metrics').html('<div class="sfm-loading">Loading metrics...</div>');
        
        $.post(sfm_ajax.ajax_url, { 
            action: 'sfm_pro_load', 
            nonce: sfm_ajax.nonce, 
            search: currentSearch 
        }, function(response) {
            if (response && response.success) {
                $('#sfm-table').html(response.data.table);
                $('#sfm-metrics').html(response.data.metrics);
            } else {
                $('#sfm-table').html('<div class="notice notice-error"><p>Failed to load data</p></div>');
            }
        }, 'json').fail(function() {
            $('#sfm-table').html('<div class="notice notice-error"><p>Request failed. Please try again.</p></div>');
        });
    }

    // Initial load
    loadTable('');

    // Search functionality
    $('#sfm-search-btn').on('click', function(e) {
        e.preventDefault();
        var searchVal = $('#sfm-search').val().trim();
        loadTable(searchVal);
    });

    $('#sfm-clear-search').on('click', function(e) {
        e.preventDefault();
        $('#sfm-search').val('');
        loadTable('');
    });

    $('#sfm-search').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            $('#sfm-search-btn').click();
        }
    });

    // Toggle installments view
    $(document).on('click', '.sfm-student-id', function(e) {
        e.preventDefault();
        var studentId = $(this).data('student-id');
        var expandRow = $('#sfm-expand-' + studentId);
        
        // Toggle current row
        expandRow.toggleClass('expanded');
        
        // Close other expanded rows
        $('.sfm-expandable-row').not(expandRow).removeClass('expanded');
    });

    // Pay installment
    $(document).on('click', '.sfm-pay-btn', function(e) {
        e.preventDefault();
        var btn = $(this);
        var installmentId = btn.data('id');
        var input = btn.siblings('.sfm-install-input');
        var amount = parseFloat(input.val());
        
        if (!amount || amount <= 0) {
            alert('Please enter a valid payment amount');
            return;
        }
        
        btn.prop('disabled', true).text('Processing...');
        
        $.post(sfm_ajax.ajax_url, {
            action: 'sfm_pro_pay',
            nonce: sfm_ajax.nonce,
            installment_id: installmentId,
            amount: amount
        }, function(response) {
            if (response && response.success) {
                loadTable(currentSearch);
            } else {
                alert(response && response.data ? response.data : 'Payment failed');
                btn.prop('disabled', false).text('Pay');
            }
        }, 'json').fail(function() {
            alert('Request failed. Please try again.');
            btn.prop('disabled', false).text('Pay');
        });
    });

    // Unpay installment
    $(document).on('click', '.sfm-unpay-btn', function(e) {
        e.preventDefault();
        if (!confirm('Are you sure you want to mark this installment as unpaid?')) {
            return;
        }
        
        var btn = $(this);
        var installmentId = btn.data('id');
        
        btn.prop('disabled', true).text('Processing...');
        
        $.post(sfm_ajax.ajax_url, {
            action: 'sfm_pro_unpay',
            nonce: sfm_ajax.nonce,
            installment_id: installmentId
        }, function(response) {
            if (response && response.success) {
                loadTable(currentSearch);
            } else {
                alert(response && response.data ? response.data : 'Action failed');
                btn.prop('disabled', false).text('Unpay');
            }
        }, 'json').fail(function() {
            alert('Request failed. Please try again.');
            btn.prop('disabled', false).text('Unpay');
        });
    });

    // Delete student
    $(document).on('click', '.sfm-delete-btn', function(e) {
        e.preventDefault();
        if (!confirm('Are you sure you want to delete this student? This action cannot be undone and will delete all associated installment records.')) {
            return;
        }
        
        var btn = $(this);
        var studentId = btn.data('student-id');
        
        btn.prop('disabled', true).text('Deleting...');
        
        $.post(sfm_ajax.ajax_url, {
            action: 'sfm_pro_delete_student',
            nonce: sfm_ajax.nonce,
            student_id: studentId
        }, function(response) {
            if (response && response.success) {
                loadTable(currentSearch);
            } else {
                alert(response && response.data ? response.data : 'Delete failed');
                btn.prop('disabled', false).text('Delete');
            }
        }, 'json').fail(function() {
            alert('Request failed. Please try again.');
            btn.prop('disabled', false).text('Delete');
        });
    });

    // Edit name
    $(document).on('click', '.sfm-edit-name', function(e) {
        e.preventDefault();
        var btn = $(this);
        var studentId = btn.data('student-id');
        var nameCell = btn.closest('td');
        var currentName = nameCell.find('.sfm-student-name').text();
        
        // Replace name with input field
        nameCell.html('\
            <input type="text" class="sfm-name-input" value="' + currentName + '">\
            <button class="sfm-save-name" data-student-id="' + studentId + '">Save</button>\
            <button class="sfm-cancel-name" data-student-id="' + studentId + '">Cancel</button>\
        ');
    });

    // Save name
    $(document).on('click', '.sfm-save-name', function(e) {
        e.preventDefault();
        var btn = $(this);
        var studentId = btn.data('student-id');
        var nameInput = btn.siblings('.sfm-name-input');
        var newName = nameInput.val().trim();
        
        if (!newName) {
            alert('Please enter a name');
            return;
        }
        
        btn.prop('disabled', true).text('Saving...');
        
        $.post(sfm_ajax.ajax_url, {
            action: 'sfm_pro_update_name',
            nonce: sfm_ajax.nonce,
            student_id: studentId,
            student_name: newName
        }, function(response) {
            if (response && response.success) {
                loadTable(currentSearch);
            } else {
                alert(response && response.data ? response.data : 'Update failed');
                btn.prop('disabled', false).text('Save');
            }
        }, 'json').fail(function() {
            alert('Request failed. Please try again.');
            btn.prop('disabled', false).text('Save');
        });
    });

    // Cancel name edit
    $(document).on('click', '.sfm-cancel-name', function(e) {
        e.preventDefault();
        loadTable(currentSearch);
    });
});
