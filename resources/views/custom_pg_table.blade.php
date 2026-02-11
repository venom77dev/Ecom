@php
    $payments = \Botble\Payment\Models\PgLists::get();
@endphp

<div class="page-body page-content">
    <div class="container-xl">
        <div class="table-wrapper">
            <div class="card has-actions has-filter">
                <div class="card-header">
                    <div class="w-100 justify-content-between d-flex flex-wrap align-items-center gap-1">
                        <div class="d-flex flex-wrap flex-md-nowrap align-items-center gap-1"></div>
                        <div class="d-flex align-items-center gap-1">
                            <button class="btn action-item btn-primary " id="createPayment" tabindex="0"
                                    aria-controls="botble-blog-tables-tag-table" type="button" aria-haspopup="dialog"
                                    aria-expanded="false">
                                    <span data-action="create">
                                        <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="24"
                                             height="24" viewBox="0 0 24 24" fill="none"
                                             stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                             stroke-linejoin="round">
                                      <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                      <path d="M12 5l0 14"></path>
                                      <path d="M5 12l14 0"></path>
                                    </svg>
                                        Create
                                    </span>
                            </button>
                            <button class="btn" type="button" data-bb-toggle="dt-buttons" onclick="window.location.reload()"
                                    data-bb-target=".buttons-reload" tabindex="0"
                                    aria-controls="botble-blog-tables-tag-table">
                                <svg class="icon icon-left" xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                     viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                     stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                    <path d="M20 11a8.1 8.1 0 0 0 -15.5 -2m-.5 -4v4h4"></path>
                                    <path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4"></path>
                                </svg>
                                Reload
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card-table">
                    <div class="table-responsive table-has-actions table-has-filter">
                        <div id="botble-blog-tables-tag-table_wrapper"
                             class="dataTables_wrapper dt-bootstrap5 no-footer">
                            <table
                                class="table card-table table-vcenter table-striped table-hover dataTable no-footer dtr-inline"
                                aria-describedby="botble-blog-tables-tag-table_info">
                                <thead>
                                <tr>
                                    <th title="ID" width="20"
                                        class="text-center no-column-visibility column-key-0 sorting sorting_desc" style="width: 20px;" aria-label="IDorderby asc"
                                        aria-sort="descending">ID
                                    </th>
                                    <th class="column-key-2 sorting">Gateway Name</th>
                                    <th class="column-key-2 sorting">PG ID</th>
                                    <th class="column-key-2 sorting">Meta ID</th>
                                    <th class="column-key-2 sorting">Slug</th>
                                    <th title="Status" width="100" class="text-center column-key-3 sorting">Status</th>
                                    <th title="Operations"
                                        class="text-center no-column-visibility text-nowrap sorting_disabled"
                                        rowspan="1" colspan="1" aria-label="Operations">Operations
                                    </th>
                                </tr>
                                </thead>
                                <tbody>
                                @if(isset($payments))
                                    @foreach($payments as $key => $item)
                                        <tr class="{{ $key % 2 == 0 ? 'even' : 'odd' }}">
                                            <td class="text-center no-column-visibility column-key-0 sorting_1">{{$item->id ?  : '--'}}</td>
                                            <td class="  text-start  column-key-1">
                                                {{$item->name ?  : '--'}}
                                            </td>
                                            <td class="column-key-2">{{$item->pg_name_id ?  : '--'}}</td>
                                            <td class="column-key-2">{{$item->pg_meta_id ?  : '--'}}</td>
                                            <td class="column-key-2">{{$item->slug ?  : '--'}}</td>
                                            <td class="  text-center  column-key-3">
                                                <span class="badge {{$item->status ? 'bg-success text-success-fg'  : 'bg-danger text-danger-fg'}}">{{$item->status ? 'Active'  : 'InActive'}}</span>
                                            </td>
                                            <td class="  text-center no-column-visibility text-nowrap">
                                                <div class="table-actions">
                                                    <div class="table-actions">
                                                        <a href="#" class="btn btn-sm btn-icon btn-primary editPayment" data-id="{{$item->id}}">
                                                            <svg class="icon" data-bs-toggle="tooltip"
                                                                 data-bs-title="Edit"
                                                                 xmlns="http://www.w3.org/2000/svg" width="24"
                                                                 height="24"
                                                                 viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                                 stroke-width="2" stroke-linecap="round"
                                                                 stroke-linejoin="round">
                                                                <path stroke="none" d="M0 0h24v24H0z"
                                                                      fill="none"></path>
                                                                <path
                                                                    d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1"></path>
                                                                <path
                                                                    d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415z"></path>
                                                                <path d="M16 5l3 3"></path>
                                                            </svg>
                                                            <span class="sr-only">Edit</span>
                                                        </a>
                                                        @if($item->name !='COD' )
                                                            <a  class="btn btn-sm btn-icon btn-danger deletePayment" data-id="{{$item->id}}" href="#">
                                                                <svg class="icon" data-bs-toggle="tooltip"
                                                                     data-bs-title="Delete"
                                                                     xmlns="http://www.w3.org/2000/svg" width="24"
                                                                     height="24"
                                                                     viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                                     stroke-width="2" stroke-linecap="round"
                                                                     stroke-linejoin="round">
                                                                    <path stroke="none" d="M0 0h24v24H0z"
                                                                          fill="none"></path>
                                                                    <path d="M4 7l16 0"></path>
                                                                    <path d="M10 11l0 6"></path>
                                                                    <path d="M14 11l0 6"></path>
                                                                    <path
                                                                        d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12"></path>
                                                                    <path
                                                                        d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3"></path>
                                                                </svg>
                                                                <span class="sr-only">Delete</span>
                                                            </a>
                                                        @endif
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                @else
                                    <tr>
                                        <td colspan="5" class="text-center">
                                            Data Not Found
                                        </td>
                                    </tr>
                                @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Create/Edit Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1" role="dialog" aria-labelledby="paymentModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="paymentModalLabel">Create Payment</h5>
                <button type="button" class="close btn btn-secondary btn-sm" data-dismiss="modal" aria-label="Close" id="closeModelBtn">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="paymentForm">
                    <input type="hidden" id="paymentId">
                    <div class="form-group">
                        <label for="el_name">Gateway Name</label>
                        <input type="text" class="form-control" id="el_name" required>
                    </div>
                    <div class="form-group">
                        <label for="el_pg_name_id">PG ID or (Api Key)</label>
                        <input type="text" class="form-control" id="el_pg_name_id" required>
                    </div>
                    <div class="form-group">
                        <label for="el_pg_meta_id">PG Meta ID or (Api secret)</label>
                        <input type="text" class="form-control" id="el_pg_meta_id" required>
                    </div>
                    <div class="form-group">
                        <label for="el_slug">Slug</label>
                        <input type="text" class="form-control" id="el_slug" required>
                    </div>
                    <div class="form-group">
                        <label for="el_label">Label</label>
                        <input type="text" class="form-control" id="el_label" required>
                    </div>
                    <div class="form-group">
                        <label for="el_description">Description</label>
                        <textarea type="text" class="form-control" id="el_description" cols="30" rows="3" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select class="form-control" id="el_status" required>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Save</button>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
    $(document).ready(function() {

        $('#createPayment').click(function() {
            $('#paymentModalLabel').text('Create New PG');
            $('#paymentForm')[0].reset();
            $('#paymentId').val('');
            $('#paymentModal').modal('show');
        });

        $('#paymentForm').submit(function(e) {
            e.preventDefault();
            let paymentId = $('#paymentId').val();
            let formData = {
                name: $('#el_name').val(),
                pg_name_id: $('#el_pg_name_id').val(),
                pg_meta_id: $('#el_pg_meta_id').val(),
                slug: $('#el_slug').val(),
                label: $('#el_label').val(),
                description: $('#el_description').val(),
                status: $('#el_status').val()
            };
            if (paymentId) {
                $.ajax({
                    url: `/api_pg_list/${paymentId}`,
                    method: 'PUT',
                    data: formData,
                    success: function(response) {
                        $('#paymentModal').modal('hide');
                        window.location.reload();
                    }
                });
            } else {
                $.ajax({
                    url: `/api_pg_list/create`,
                    method: 'POST',
                    data: formData,
                    success: function(response) {
                        $('#paymentModal').modal('hide');
                        window.location.reload();
                    }
                });
            }
        });

        $('body').on('click', '.editPayment', function() {
            let paymentId = $(this).data('id');
            $.ajax({
                url: `/api_pg_list/${paymentId}`,
                method: 'GET',
                success: function(response) {
                    $('#paymentModalLabel').text('Edit Payment');
                    $('#paymentId').val(response.id);
                    $('#el_name').val(response.name);
                    $('#el_pg_name_id').val(response.pg_name_id);
                    $('#el_pg_meta_id').val(response.pg_meta_id);
                    $('#el_slug').val(response.slug);
                    $('#el_label').val(response.label);
                    $('#el_description').val(response.description);
                    $('#el_status').val(response.status);
                    $('#paymentModal').modal('show');
                    if (response.name === 'COD') {
                        disableFields();
                    } else {
                        enableFields();
                    }
                }
            });
        });

        $('body').on('click', '.deletePayment', function() {
            let paymentId = $(this).data('id');
            if (confirm('Do you really want to delete this payment?')) {
                $.ajax({
                    url: `/api_pg_list/${paymentId}`,
                    method: 'DELETE',
                    success: function(response) {
                        window.location.reload();
                    }
                });
            }
        });
        $('#closeModelBtn').click(function (){
            $('#paymentModal').modal('hide');
        });
    });

    function disableFields() {
        $('#el_name').prop('disabled', true);
        $('#el_pg_name_id').prop('disabled', true);
        $('#el_pg_meta_id').prop('disabled', true);
        $('#el_slug').prop('disabled', true);
    }

    function enableFields() {
        $('#el_name').prop('disabled', false);
        $('#el_pg_name_id').prop('disabled', false);
        $('#el_pg_meta_id').prop('disabled', false);
        $('#el_slug').prop('disabled', false);
    }
</script>

