@extends('sag.layouts.app')

@push('style')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.17/themes/default/style.min.css">
    <style>
        #admin-menu-tree {
            min-height: 320px;
        }

        .menu-form-placeholder {
            min-height: 320px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
        }
    </style>
@endpush

@section('content')
    @include('sag.components.page_header')

    <section class="content">
        <div
            id="menu-manager"
            data-tree-url="{{ route('sag.menu.tree') }}"
            data-store-url="{{ route('sag.menu.store') }}"
            data-update-url="{{ route('sag.menu.update', ['adminMenuItem' => '__ID__']) }}"
            data-delete-url="{{ route('sag.menu.destroy', ['adminMenuItem' => '__ID__']) }}"
            data-reorder-url="{{ route('sag.menu.reorder') }}"
        >
            <div class="row">
                <div class="col-md-5">
                    <div class="card card-primary">
                        <div class="card-header">
                            <h3 class="card-title">Menu tree</h3>
                            <div class="card-tools">
                                <button type="button" id="btnCreateRoot" class="btn btn-tool" title="Create root menu">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <p class="text-muted">
                                Drag menu items to change their parent or order. Right-click an item for more actions.
                            </p>
                            <div id="admin-menu-tree"></div>
                        </div>
                    </div>
                </div>

                <div class="col-md-7">
                    <div class="card card-info">
                        <div class="card-header">
                            <h3 class="card-title" id="menuFormTitle">Create menu item</h3>
                        </div>
                        <form id="menuForm">
                            <div class="card-body">
                                <div id="menuFormErrors" class="alert alert-danger d-none"></div>
                                <input type="hidden" id="menuItemId">
                                <input type="hidden" id="parentId" name="parent_id">

                                <div class="form-group">
                                    <label for="parentLabel">Parent</label>
                                    <input type="text" id="parentLabel" class="form-control" value="Root" readonly>
                                </div>

                                <div class="form-group">
                                    <label for="menuTitle">Title <span class="text-danger">*</span></label>
                                    <input type="text" id="menuTitle" name="title" class="form-control" required>
                                </div>

                                <div class="form-group">
                                    <label for="menuKey">Local key</label>
                                    <input type="text" id="menuKey" name="key" class="form-control">
                                    <small class="form-text text-muted">
                                        Leave empty to generate from title. Child keys are prefixed with the parent key.
                                    </small>
                                </div>

                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="form-group">
                                            <label for="menuIcon">Font Awesome icon</label>
                                            <input
                                                type="text"
                                                id="menuIcon"
                                                name="icon"
                                                class="form-control"
                                                placeholder="fas fa-circle"
                                            >
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="menuSortOrder">Sort order</label>
                                            <input
                                                type="number"
                                                min="0"
                                                id="menuSortOrder"
                                                name="sort_order"
                                                class="form-control"
                                            >
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="menuLinkType">Link type <span class="text-danger">*</span></label>
                                    <select id="menuLinkType" name="link_type" class="form-control" required>
                                        @foreach($linkTypes as $linkType)
                                            <option value="{{ $linkType->value }}">{{ $linkType->label() }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group" id="targetGroup">
                                    <label for="menuTarget">Target</label>
                                    <input type="text" id="menuTarget" name="target" class="form-control">
                                    <small class="form-text text-muted" id="targetHelp"></small>
                                </div>

                                <div class="form-group" id="parametersGroup">
                                    <label for="menuParameters">Route parameters (JSON)</label>
                                    <textarea
                                        id="menuParameters"
                                        name="parameters"
                                        class="form-control"
                                        rows="3"
                                        placeholder='{"id": 1}'
                                    ></textarea>
                                </div>

                                <div class="row">
                                    <div class="col-md-7">
                                        <div class="form-group">
                                            <label for="menuPermission">Permission</label>
                                            <input type="text" id="menuPermission" name="permission" class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-md-5">
                                        <div class="form-group">
                                            <label for="menuTargetWindow">Open in</label>
                                            <select id="menuTargetWindow" name="target_window" class="form-control">
                                                <option value="_self">Current window</option>
                                                <option value="_blank">New window</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="custom-control custom-switch">
                                    <input
                                        type="checkbox"
                                        class="custom-control-input"
                                        id="menuIsActive"
                                        name="is_active"
                                        value="1"
                                        checked
                                    >
                                    <label class="custom-control-label" for="menuIsActive">Active</label>
                                </div>
                            </div>

                            <div class="card-footer">
                                <button type="button" id="btnResetMenu" class="btn btn-default">Reset</button>
                                <button type="button" id="btnDeleteMenu" class="btn btn-danger d-none">Delete</button>
                                <button type="submit" class="btn btn-info float-right">Save</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@push('script')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.17/jstree.min.js"></script>
    <script>
        $(function () {
            if ($('link[href*="jstree"]').length === 0) {
                $('<link>', {
                    rel: 'stylesheet',
                    href: 'https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.17/themes/default/style.min.css'
                }).appendTo('head');
            }

            const manager = $('#menu-manager');
            const treeElement = $('#admin-menu-tree');
            const csrfToken = '{{ csrf_token() }}';
            let isRefreshing = false;

            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                }
            });

            function endpoint(name, id) {
                const value = manager.attr('data-' + name);
                return id ? value.replace('__ID__', id) : value;
            }

            function showErrors(xhr) {
                const errorBox = $('#menuFormErrors').empty().removeClass('d-none');
                const errors = xhr.responseJSON && xhr.responseJSON.errors
                    ? xhr.responseJSON.errors
                    : {error: [xhr.responseJSON && xhr.responseJSON.message
                        ? xhr.responseJSON.message
                        : 'Unable to save the menu item.']};

                Object.keys(errors).forEach(function (field) {
                    errors[field].forEach(function (message) {
                        errorBox.append($('<div>').text(message));
                    });
                });
            }

            function clearErrors() {
                $('#menuFormErrors').empty().addClass('d-none');
            }

            function linkTypeChanged() {
                const type = $('#menuLinkType').val();
                const help = {
                    none: 'This item only groups child menu items.',
                    route: 'Laravel route name, for example sag.admin.index.',
                    root_path: 'Path from the application root, for example /products.',
                    admin_path: 'Path relative to the configured admin prefix, for example admins.',
                    url: 'Full HTTP or HTTPS URL.'
                };

                $('#menuTarget').prop('disabled', type === 'none');
                $('#targetHelp').text(help[type] || '');
                $('#parametersGroup').toggle(type === 'route');

                if (type === 'none') {
                    $('#menuTarget').val('');
                    $('#menuParameters').val('');
                }
            }

            function resetForm(parentNode) {
                clearErrors();
                $('#menuForm')[0].reset();
                $('#menuItemId').val('');
                $('#menuFormTitle').text('Create menu item');
                $('#btnDeleteMenu').addClass('d-none');
                $('#menuIsActive').prop('checked', true);
                $('#menuTargetWindow').val('_self');
                $('#menuLinkType').val('none');

                if (parentNode && parentNode.id !== '#') {
                    $('#parentId').val(parentNode.id);
                    $('#parentLabel').val(parentNode.data.key);
                } else {
                    $('#parentId').val('');
                    $('#parentLabel').val('Root');
                }

                linkTypeChanged();
            }

            function fillForm(node) {
                const data = node.data;
                const parentNode = node.parent === '#'
                    ? null
                    : treeElement.jstree(true).get_node(node.parent);

                clearErrors();
                $('#menuItemId').val(data.id);
                $('#parentId').val(data.parent_id || '');
                $('#parentLabel').val(parentNode ? parentNode.data.key : 'Root');
                $('#menuTitle').val(data.title);
                $('#menuKey').val(data.local_key);
                $('#menuIcon').val(data.icon || '');
                $('#menuSortOrder').val(data.sort_order);
                $('#menuLinkType').val(data.link_type);
                $('#menuTarget').val(data.target || '');
                $('#menuParameters').val(
                    data.parameters ? JSON.stringify(data.parameters, null, 2) : ''
                );
                $('#menuPermission').val(data.permission || '');
                $('#menuTargetWindow').val(data.target_window || '_self');
                $('#menuIsActive').prop('checked', Boolean(data.is_active));
                $('#menuFormTitle').text('Edit menu item');
                $('#btnDeleteMenu').removeClass('d-none');
                linkTypeChanged();
            }

            function refreshTree(selectedId) {
                isRefreshing = true;
                treeElement.one('refresh.jstree', function () {
                    isRefreshing = false;
                    if (selectedId) {
                        treeElement.jstree(true).select_node(String(selectedId));
                    }
                });
                treeElement.jstree(true).refresh();
            }

            function treePayload() {
                const tree = treeElement.jstree(true);
                const flatNodes = tree.get_json('#', {flat: true});
                const positions = {};

                return flatNodes.map(function (node) {
                    const parent = node.parent === '#' ? null : Number(node.parent);
                    const groupKey = parent === null ? 'root' : String(parent);
                    positions[groupKey] = positions[groupKey] || 0;
                    positions[groupKey] += 10;

                    return {
                        id: Number(node.id),
                        parent_id: parent,
                        sort_order: positions[groupKey]
                    };
                });
            }

            function persistTreeOrder(selectedId) {
                $.post(endpoint('reorder-url'), {items: treePayload()})
                    .done(function (response) {
                        toastr.success(response.message);
                        refreshTree(selectedId);
                    })
                    .fail(function (xhr) {
                        showErrors(xhr);
                        refreshTree(selectedId);
                    });
            }

            treeElement
                .on('select_node.jstree', function (event, data) {
                    fillForm(data.node);
                })
                .on('move_node.jstree', function (event, data) {
                    if (!isRefreshing) {
                        persistTreeOrder(data.node.id);
                    }
                })
                .jstree({
                    core: {
                        data: {
                            url: endpoint('tree-url'),
                            dataType: 'json'
                        },
                        check_callback: function (operation, node, parent) {
                            if (operation !== 'move_node') {
                                return true;
                            }

                            return parent.id !== node.id
                                && (node.children_d || []).indexOf(parent.id) === -1;
                        },
                        multiple: false,
                        themes: {
                            responsive: true
                        }
                    },
                    plugins: ['contextmenu', 'dnd', 'wholerow'],
                    contextmenu: {
                        items: function (node) {
                            return {
                                createChild: {
                                    label: 'Create child',
                                    icon: 'fas fa-plus',
                                    action: function () {
                                        resetForm(node);
                                        $('#menuTitle').trigger('focus');
                                    }
                                },
                                edit: {
                                    label: 'Edit',
                                    icon: 'fas fa-pencil-alt',
                                    action: function () {
                                        fillForm(node);
                                    }
                                },
                                remove: {
                                    label: 'Delete',
                                    icon: 'fas fa-trash',
                                    action: function () {
                                        deleteItem(node.id);
                                    }
                                }
                            };
                        }
                    }
                });

            $('#menuLinkType').on('change', linkTypeChanged);

            $('#btnCreateRoot').on('click', function () {
                resetForm(null);
                $('#menuTitle').trigger('focus');
            });

            $('#btnResetMenu').on('click', function () {
                const selected = treeElement.jstree(true).get_selected(true)[0];
                resetForm(selected || null);
            });

            $('#menuForm').on('submit', function (event) {
                event.preventDefault();
                clearErrors();

                const id = $('#menuItemId').val();
                const formData = {
                    parent_id: $('#parentId').val() || null,
                    title: $('#menuTitle').val(),
                    key: $('#menuKey').val(),
                    icon: $('#menuIcon').val(),
                    sort_order: $('#menuSortOrder').val() || null,
                    link_type: $('#menuLinkType').val(),
                    target: $('#menuTarget').prop('disabled') ? null : $('#menuTarget').val(),
                    parameters: $('#menuParameters').val(),
                    permission: $('#menuPermission').val(),
                    target_window: $('#menuTargetWindow').val(),
                    is_active: $('#menuIsActive').is(':checked') ? 1 : 0
                };

                $.ajax({
                    url: id ? endpoint('update-url', id) : endpoint('store-url'),
                    method: id ? 'PUT' : 'POST',
                    data: formData
                })
                    .done(function (response) {
                        toastr.success(response.message);
                        refreshTree(response.item.id);
                    })
                    .fail(showErrors);
            });

            function deleteItem(id) {
                Swal.fire({
                    title: 'Delete this menu item?',
                    text: 'Direct children will be moved to the root level.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Delete',
                    confirmButtonColor: '#dc3545'
                }).then(function (result) {
                    if (!result.isConfirmed) {
                        return;
                    }

                    $.ajax({
                        url: endpoint('delete-url', id),
                        method: 'DELETE'
                    })
                        .done(function (response) {
                            toastr.success(response.message);
                            resetForm(null);
                            refreshTree();
                        })
                        .fail(showErrors);
                });
            }

            $('#btnDeleteMenu').on('click', function () {
                const id = $('#menuItemId').val();
                if (id) {
                    deleteItem(id);
                }
            });

            resetForm(null);
        });
    </script>
@endpush
