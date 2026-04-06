(function ($) {
    'use strict';

    var state = {
        customerId: 0,
        customerName: '',
        customerEmail: '',
        subscriptions: [],
    };

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    function showLoading() {
        $('#lsms-loading').removeClass('lsms-hidden');
    }

    function hideLoading() {
        $('#lsms-loading').addClass('lsms-hidden');
    }

    function showStep(step) {
        $('.lsms-card').addClass('lsms-hidden');
        $('#lsms-step-' + step).removeClass('lsms-hidden');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function money(val) {
        return '$' + parseFloat(val).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    function ajax(action, data) {
        data.action = action;
        data.nonce = lsms.nonce;
        return $.post(lsms.ajax_url, data);
    }

    // -------------------------------------------------------------------------
    // Step 1: Search customers
    // -------------------------------------------------------------------------

    function searchCustomers() {
        var search = $('#lsms-customer-search').val().trim();
        if (!search) return;

        showLoading();
        ajax('lsms_search_customers', { search: search })
            .done(function (res) {
                hideLoading();
                if (!res.success) {
                    alert(res.data || 'Search failed.');
                    return;
                }
                renderCustomerResults(res.data);
            })
            .fail(function () {
                hideLoading();
                alert('Request failed.');
            });
    }

    function renderCustomerResults(customers) {
        var $container = $('#lsms-customer-results');
        $container.empty();

        if (!customers.length) {
            $container.html('<p>No customers found.</p>');
            return;
        }

        var $list = $('<div class="lsms-customer-list"></div>');

        customers.forEach(function (c) {
            var $item = $(
                '<div class="lsms-customer-item" data-id="' + c.id + '">' +
                    '<div><span class="name">' + escHtml(c.name) + '</span> &mdash; <span class="email">' + escHtml(c.email) + '</span></div>' +
                    '<span class="id">ID: ' + c.id + '</span>' +
                '</div>'
            );

            $item.on('click', function () {
                state.customerId = c.id;
                state.customerName = c.name;
                state.customerEmail = c.email;
                loadSubscriptions();
            });

            $list.append($item);
        });

        $container.append($list);
    }

    // -------------------------------------------------------------------------
    // Step 2: Load subscriptions
    // -------------------------------------------------------------------------

    function loadSubscriptions() {
        showLoading();
        ajax('lsms_get_subscriptions', { customer_id: state.customerId })
            .done(function (res) {
                hideLoading();
                if (!res.success) {
                    alert(res.data || 'Failed to load subscriptions.');
                    return;
                }
                state.subscriptions = res.data;
                renderSubscriptions();
                showStep('subscriptions');
            })
            .fail(function () {
                hideLoading();
                alert('Request failed.');
            });
    }

    function renderSubscriptions() {
        // Customer info bar.
        $('#lsms-customer-info').html(
            '<div class="lsms-customer-bar">' +
                '<div><span class="name">' + escHtml(state.customerName) + '</span> &mdash; ' + escHtml(state.customerEmail) + '</div>' +
                '<div>Customer ID: ' + state.customerId + ' &bull; ' + state.subscriptions.length + ' active subscription(s)</div>' +
            '</div>'
        );

        var $tbody = $('#lsms-sub-table tbody');
        $tbody.empty();

        var totalCredit = 0;

        state.subscriptions.forEach(function (sub) {
            var itemsHtml = '';
            sub.items.forEach(function (item) {
                var badge = item.type === 'vehicle'
                    ? '<span class="lsms-type-vehicle">Vehicle</span>'
                    : '<span class="lsms-type-asset">Asset</span>';
                itemsHtml += badge + ' ' + escHtml(item.name) + ' (x' + item.quantity + ' @ ' + money(item.unit_price) + ')<br>';
            });

            var periodClass = sub.billing_period === 'month' ? 'monthly' : 'yearly';
            var periodLabel = 'Every ' + sub.billing_interval + ' ' + sub.billing_period + '(s)';

            var proration = sub.proration || {};
            totalCredit += proration.credit || 0;

            var $row = $(
                '<tr>' +
                    '<td class="check-column"><input type="checkbox" class="lsms-sub-checkbox" value="' + sub.id + '" checked /></td>' +
                    '<td><a href="post.php?post=' + sub.id + '&action=edit" target="_blank">#' + sub.id + '</a></td>' +
                    '<td>' + itemsHtml + '</td>' +
                    '<td><span class="lsms-period-' + periodClass + '">' + periodLabel + '</span></td>' +
                    '<td>' + money(sub.total) + '</td>' +
                    '<td>' + (sub.last_paid ? sub.last_paid.substring(0, 10) : 'N/A') + '</td>' +
                    '<td>' + (proration.paid_through || 'N/A') + '</td>' +
                    '<td>' + (proration.days_remaining || 0) + '</td>' +
                    '<td>' + money(proration.credit || 0) + '</td>' +
                '</tr>'
            );

            $tbody.append($row);
        });

        $('#lsms-total-credit').text(money(totalCredit));
    }

    // -------------------------------------------------------------------------
    // Step 3: Preview merge
    // -------------------------------------------------------------------------

    function getPricingOverrides() {
        return {
            price_asset: $('#lsms-price-asset').val(),
            price_vehicle: $('#lsms-price-vehicle').val(),
            discount_pct: $('#lsms-discount-pct').val(),
        };
    }

    function previewMerge() {
        var selectedIds = [];
        $('.lsms-sub-checkbox:checked').each(function () {
            selectedIds.push(parseInt($(this).val(), 10));
        });

        if (selectedIds.length < 2) {
            alert('Please select at least 2 subscriptions to merge.');
            return;
        }

        var pricing = getPricingOverrides();

        showLoading();
        ajax('lsms_preview_merge', {
            customer_id: state.customerId,
            subscription_ids: selectedIds,
            price_asset: pricing.price_asset,
            price_vehicle: pricing.price_vehicle,
            discount_pct: pricing.discount_pct,
        })
            .done(function (res) {
                hideLoading();
                if (!res.success) {
                    alert(res.data || 'Failed to generate preview.');
                    return;
                }
                renderPreview(res.data, selectedIds);
                showStep('preview');
            })
            .fail(function () {
                hideLoading();
                alert('Request failed.');
            });
    }

    function renderPreview(data, selectedIds) {
        var preview = data.preview;
        var stripe = data.stripe;

        var html = '';

        // Summary boxes.
        html += '<div class="lsms-preview-summary">';
        html += summaryBox('Total Devices', preview.asset_qty + preview.vehicle_qty, false);
        html += summaryBox('Asset Devices', preview.asset_qty, false);
        html += summaryBox('Vehicle Devices', preview.vehicle_qty, false);
        html += summaryBox('Annual Total', money(preview.annual_total), false);
        html += summaryBox('Proration Credit', money(preview.proration_credit), true);
        html += summaryBox('Prepaid Days', preview.prepaid_days, false);
        html += summaryBox('First Renewal', preview.renewal_date, true);
        html += '</div>';

        // Line items table.
        html += '<h3>New Subscription Line Items</h3>';
        html += '<table class="lsms-preview-table">';
        html += '<thead><tr><th>Type</th><th>Product</th><th>Qty</th><th>Unit Price</th><th>Line Total</th></tr></thead>';
        html += '<tbody>';

        preview.line_items.forEach(function (item) {
            var badge = item.type === 'vehicle'
                ? '<span class="lsms-type-vehicle">Vehicle</span>'
                : '<span class="lsms-type-asset">Asset</span>';

            // Collect IMEI numbers from metadata.
            var imeis = [];
            if (item.meta_data && item.meta_data.length) {
                item.meta_data.forEach(function (m) {
                    if (m.key.toLowerCase().indexOf('imei') !== -1) {
                        imeis.push(m.value + ' (from Sub #' + m.from_sub + ')');
                    }
                });
            }

            var metaHtml = '';
            if (imeis.length) {
                metaHtml = '<br><small style="color:#666;">IMEIs: ' + imeis.map(escHtml).join(', ') + '</small>';
            }

            html += '<tr>';
            html += '<td>' + badge + '</td>';
            html += '<td>' + escHtml(item.name) + metaHtml + '</td>';
            html += '<td>' + item.quantity + '</td>';
            html += '<td>' + money(item.unit_price) + '</td>';
            html += '<td>' + money(item.line_total) + '</td>';
            html += '</tr>';
        });

        html += '</tbody>';
        html += '<tfoot><tr><td colspan="4" style="text-align:right;">Annual Total</td><td>' + money(preview.annual_total) + '</td></tr></tfoot>';
        html += '</table>';

        // Payment details.
        if (stripe) {
            html += '<h3>Payment Method</h3>';
            html += '<p><strong>Method:</strong> ' + escHtml(stripe.payment_method_title || stripe.payment_method) + '<br>';
            html += '<strong>Stripe Customer:</strong> ' + escHtml(stripe.stripe_customer_id || 'N/A') + '<br>';
            html += '<strong>Stripe Payment ID:</strong> ' + escHtml(stripe.stripe_source_id || 'N/A') + '</p>';
        }

        // Subscriptions to cancel.
        html += '<h3>Subscriptions to Cancel (' + selectedIds.length + ')</h3>';
        html += '<p>' + selectedIds.map(function (id) { return '#' + id; }).join(', ') + '</p>';

        $('#lsms-preview-content').html(html);

        // Store selected IDs for execution.
        state.selectedIds = selectedIds;
    }

    function summaryBox(label, value, highlight) {
        var cls = highlight ? ' highlight' : '';
        return '<div class="lsms-summary-box' + cls + '">' +
            '<div class="label">' + label + '</div>' +
            '<div class="value">' + value + '</div>' +
        '</div>';
    }

    // -------------------------------------------------------------------------
    // Step 4: Execute merge
    // -------------------------------------------------------------------------

    function executeMerge() {
        if (!confirm('Are you sure you want to merge these subscriptions? This will cancel the selected subscriptions and create a new consolidated one.')) {
            return;
        }

        var pricing = getPricingOverrides();

        showLoading();
        ajax('lsms_execute_merge', {
            customer_id: state.customerId,
            subscription_ids: state.selectedIds,
            price_asset: pricing.price_asset,
            price_vehicle: pricing.price_vehicle,
            discount_pct: pricing.discount_pct,
        })
            .done(function (res) {
                hideLoading();
                if (!res.success) {
                    alert('Error: ' + (res.data || 'Merge failed.'));
                    return;
                }
                renderResult(res.data);
                showStep('result');
            })
            .fail(function () {
                hideLoading();
                alert('Request failed.');
            });
    }

    function renderResult(data) {
        var html = '';

        html += '<div class="lsms-success">';
        html += '<h3>Merge Complete!</h3>';
        html += '<p><strong>New Subscription:</strong> <a href="post.php?post=' + data.new_subscription_id + '&action=edit" target="_blank">#' + data.new_subscription_id + '</a></p>';
        html += '<p><strong>Cancelled Subscriptions:</strong> ' + data.cancelled.map(function (id) {
            return '<a href="post.php?post=' + id + '&action=edit" target="_blank">#' + id + '</a>';
        }).join(', ') + '</p>';
        html += '</div>';

        html += '<p><a href="post.php?post=' + data.new_subscription_id + '&action=edit" class="button button-primary">View New Subscription</a> ';
        html += '<a href="admin.php?page=lsms-merge-subscriptions" class="button">Merge Another</a></p>';

        $('#lsms-result-content').html(html);
    }

    // -------------------------------------------------------------------------
    // Utilities
    // -------------------------------------------------------------------------

    function escHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // -------------------------------------------------------------------------
    // Event bindings
    // -------------------------------------------------------------------------

    $(function () {
        $('#lsms-search-btn').on('click', searchCustomers);
        $('#lsms-customer-search').on('keypress', function (e) {
            if (e.which === 13) searchCustomers();
        });

        $('#lsms-preview-btn').on('click', previewMerge);
        $('#lsms-execute-btn').on('click', executeMerge);

        $('#lsms-back-search').on('click', function () {
            showStep('search');
        });

        $('#lsms-back-subs').on('click', function () {
            showStep('subscriptions');
        });

        // Select all checkbox.
        $('#lsms-select-all').on('change', function () {
            var checked = $(this).is(':checked');
            $('.lsms-sub-checkbox').prop('checked', checked);
        });
    });

})(jQuery);
