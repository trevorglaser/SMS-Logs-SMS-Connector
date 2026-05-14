<?php
if (!defined('FREEPBX_IS_AUTH') || !FREEPBX_IS_AUTH) { die('No direct script access allowed'); }
?>

<div id="smslog-wrap">

    <div class="row">
        <div class="col-sm-12">
            <h1><?php echo _('SMS Event Logging'); ?></h1>
            <p class="text-muted" style="margin-top:-8px;font-size:13px;">
                <?php echo _('Reporting on data from the SMS Connector module (sms_messages)'); ?>
            </p>
        </div>
    </div>

    <!-- Stat cards -->
    <div class="row" id="smslog-stats-row">
        <div class="col-xs-6 col-sm-2 col-lg-1">
            <div class="smslog-stat-card">
                <div class="smslog-stat-label"><?php echo _('Total'); ?></div>
                <div class="smslog-stat-value" id="stat-total">—</div>
            </div>
        </div>
        <div class="col-xs-6 col-sm-2 col-lg-1">
            <div class="smslog-stat-card smslog-stat-outbound">
                <div class="smslog-stat-label"><?php echo _('Outbound'); ?></div>
                <div class="smslog-stat-value" id="stat-outbound">—</div>
            </div>
        </div>
        <div class="col-xs-6 col-sm-2 col-lg-1">
            <div class="smslog-stat-card smslog-stat-inbound">
                <div class="smslog-stat-label"><?php echo _('Inbound'); ?></div>
                <div class="smslog-stat-value" id="stat-inbound">—</div>
            </div>
        </div>
        <div class="col-xs-6 col-sm-2 col-lg-1">
            <div class="smslog-stat-card smslog-stat-internal">
                <div class="smslog-stat-label"><?php echo _('Internal'); ?></div>
                <div class="smslog-stat-value" id="stat-internal">—</div>
            </div>
        </div>
        <div class="col-xs-6 col-sm-2 col-lg-1">
            <div class="smslog-stat-card smslog-stat-delivered">
                <div class="smslog-stat-label"><?php echo _('Delivered'); ?></div>
                <div class="smslog-stat-value" id="stat-delivered">—</div>
            </div>
        </div>
        <div class="col-xs-6 col-sm-2 col-lg-1">
            <div class="smslog-stat-card smslog-stat-failed">
                <div class="smslog-stat-label"><?php echo _('Undelivered'); ?></div>
                <div class="smslog-stat-value" id="stat-failed">—</div>
            </div>
        </div>
        <div class="col-xs-6 col-sm-2 col-lg-1">
            <div class="smslog-stat-card smslog-stat-unread">
                <div class="smslog-stat-label"><?php echo _('Unread'); ?></div>
                <div class="smslog-stat-value" id="stat-unread">—</div>
            </div>
        </div>
    </div>

    <!-- Volume chart -->
    <div class="row" style="margin-bottom:8px;">
        <div class="col-sm-12">
            <div class="smslog-chart-wrap">
                <div class="smslog-chart-header">
                    <span><?php echo _('Message Volume'); ?></span>
                    <div class="smslog-chart-legend">
                        <span class="smslog-legend-dot smslog-legend-outbound"></span><?php echo _('Outbound'); ?>
                        <span class="smslog-legend-dot smslog-legend-inbound" style="margin-left:12px;"></span><?php echo _('Inbound'); ?>
                        <span class="smslog-legend-dot smslog-legend-internal" style="margin-left:12px;"></span><?php echo _('Internal'); ?>
                        <select id="smslog-volume-days" class="form-control input-xs" style="display:inline-block;width:auto;margin-left:16px;">
                            <option value="7"><?php echo _('Last 7 days'); ?></option>
                            <option value="30" selected><?php echo _('Last 30 days'); ?></option>
                            <option value="90"><?php echo _('Last 90 days'); ?></option>
                        </select>
                    </div>
                </div>
                <canvas id="smslog-volume-chart" height="80"></canvas>
            </div>
        </div>
    </div>

    <!-- Filter bar -->
    <div class="row">
        <div class="col-sm-12">
            <div class="panel panel-default smslog-filter-panel">
                <div class="panel-body">
                    <form id="smslog-filter-form" class="form-inline">

                        <div class="form-group">
                            <label><?php echo _('From date'); ?></label>
                            <input type="date" id="filter-date-from" name="date_from" class="form-control input-sm">
                        </div>
                        <div class="form-group">
                            <label><?php echo _('To date'); ?></label>
                            <input type="date" id="filter-date-to" name="date_to" class="form-control input-sm">
                        </div>
                        <div class="form-group">
                            <label><?php echo _('Direction'); ?></label>
                            <select id="filter-direction" name="direction" class="form-control input-sm">
                                <option value=""><?php echo _('All'); ?></option>
                                <option value="inbound"><?php echo _('Inbound'); ?></option>
                                <option value="outbound"><?php echo _('Outbound'); ?></option>
                                <option value="internal"><?php echo _('Internal'); ?></option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><?php echo _('Delivered'); ?></label>
                            <select id="filter-delivered" name="delivered" class="form-control input-sm">
                                <option value=""><?php echo _('All'); ?></option>
                                <option value="1"><?php echo _('Yes'); ?></option>
                                <option value="0"><?php echo _('No'); ?></option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><?php echo _('Read'); ?></label>
                            <select id="filter-read" name="read_flag" class="form-control input-sm">
                                <option value=""><?php echo _('All'); ?></option>
                                <option value="1"><?php echo _('Read'); ?></option>
                                <option value="0"><?php echo _('Unread'); ?></option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><?php echo _('Adaptor'); ?></label>
                            <select id="filter-adaptor" name="adaptor" class="form-control input-sm">
                                <option value=""><?php echo _('All'); ?></option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><?php echo _('DID'); ?></label>
                            <select id="filter-did" name="did" class="form-control input-sm">
                                <option value=""><?php echo _('All'); ?></option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><?php echo _('From number'); ?></label>
                            <input type="text" id="filter-src" name="src" class="form-control input-sm" placeholder="e.g. 5551234567">
                        </div>
                        <div class="form-group">
                            <label><?php echo _('To number'); ?></label>
                            <input type="text" id="filter-dst" name="dst" class="form-control input-sm" placeholder="e.g. 5559876543">
                        </div>
                        <div class="form-group">
                            <label><?php echo _('Thread ID'); ?></label>
                            <input type="text" id="filter-threadid" name="threadid" class="form-control input-sm" placeholder="<?php echo _('Exact thread ID'); ?>">
                        </div>
                        <div class="form-group">
                            <label><?php echo _('Search'); ?></label>
                            <input type="text" id="filter-search" name="search" class="form-control input-sm" placeholder="<?php echo _('Number, name, or message text'); ?>">
                        </div>

                        <div class="smslog-filter-actions">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fa fa-search"></i> <?php echo _('Search'); ?>
                            </button>
                            <button type="button" id="smslog-reset-btn" class="btn btn-default btn-sm">
                                <i class="fa fa-times"></i> <?php echo _('Reset'); ?>
                            </button>
                            <button type="button" id="smslog-export-btn" class="btn btn-success btn-sm">
                                <i class="fa fa-download"></i> <?php echo _('Export CSV'); ?>
                            </button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Data table -->
    <div class="row">
        <div class="col-sm-12">
            <div class="panel panel-default">
                <div class="panel-body smslog-table-wrap">

                    <div class="smslog-table-toolbar">
                        <div id="smslog-result-count" class="smslog-result-count"></div>
                        <div class="smslog-per-page-wrap">
                            <label><?php echo _('Show'); ?></label>
                            <select id="smslog-per-page" class="form-control input-xs">
                                <option value="25">25</option>
                                <option value="50" selected>50</option>
                                <option value="100">100</option>
                                <option value="250">250</option>
                            </select>
                            <label><?php echo _('entries'); ?></label>
                        </div>
                    </div>

                    <table id="smslog-table" class="table table-striped table-hover table-condensed">
                        <thead>
                            <tr>
                                <th class="smslog-sortable" data-col="tx_rx_datetime"><?php echo _('Date / Time'); ?> <i class="fa fa-sort"></i></th>
                                <th class="smslog-sortable" data-col="direction"><?php echo _('Dir'); ?> <i class="fa fa-sort"></i></th>
                                <th class="smslog-sortable" data-col="from"><?php echo _('From'); ?> <i class="fa fa-sort"></i></th>
                                <th class="smslog-sortable" data-col="to"><?php echo _('To'); ?> <i class="fa fa-sort"></i></th>
                                <th class="smslog-sortable" data-col="cnam"><?php echo _('CNAM'); ?> <i class="fa fa-sort"></i></th>
                                <th><?php echo _('Message'); ?></th>
                                <th class="smslog-sortable" data-col="delivered"><?php echo _('Delivered'); ?> <i class="fa fa-sort"></i></th>
                                <th><?php echo _('Read'); ?></th>
                                <th class="smslog-sortable" data-col="adaptor"><?php echo _('Adaptor'); ?> <i class="fa fa-sort"></i></th>
                                <th><?php echo _('DID'); ?></th>
                                <th><?php echo _('Detail'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="smslog-tbody">
                            <tr>
                                <td colspan="11" class="text-center text-muted">
                                    <i class="fa fa-spinner fa-spin"></i> <?php echo _('Loading…'); ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="smslog-pagination-wrap">
                        <ul class="pagination pagination-sm" id="smslog-pagination"></ul>
                    </div>

                </div>
            </div>
        </div>
    </div>

</div>

<!-- Detail Modal -->
<div class="modal fade" id="smslog-detail-modal" tabindex="-1" role="dialog" aria-labelledby="smslog-detail-title">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title" id="smslog-detail-title"><?php echo _('SMS Event Detail'); ?></h4>
            </div>
            <div class="modal-body" id="smslog-detail-body">
                <p class="text-center"><i class="fa fa-spinner fa-spin"></i></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _('Close'); ?></button>
            </div>
        </div>
    </div>
</div>
