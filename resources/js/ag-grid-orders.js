import { createGrid, ModuleRegistry, AllCommunityModule } from 'ag-grid-community';
import 'ag-grid-community/styles/ag-grid.css';
import 'ag-grid-community/styles/ag-theme-alpine.css';

ModuleRegistry.registerModules([AllCommunityModule]);

window.initOrdersGrid = function (wireId) {
    const wire = window.Livewire.find(wireId);

    const pendingChanges = {};

    const columnDefs = [
        {
            headerName: '',
            checkboxSelection: true,
            headerCheckboxSelection: true,
            width: 48,
            pinned: 'left',
            suppressSizeToFit: true,
        },
        { field: 'id', headerName: 'ID', width: 90, editable: false },
        { field: 'status', headerName: 'Status', editable: true, width: 140 },
        { field: 'customer', headerName: 'Customer', editable: false, flex: 1 },
        { field: 'customer_email', headerName: 'Email', editable: false, flex: 1 },
        { field: 'total', headerName: 'Total', editable: false, width: 120 },
        { field: 'created_at', headerName: 'Created', editable: false, width: 160 },
    ];

    const gridOptions = {
        columnDefs,
        rowData: [],
        rowSelection: 'multiple',
        suppressRowClickSelection: true,
        defaultColDef: {
            sortable: true,
            resizable: true,
        },
        onCellValueChanged(event) {
            const id = event.data.id;
            const field = event.colDef.field;
            const value = event.newValue;

            if (!pendingChanges[id]) {
                pendingChanges[id] = {};
            }
            pendingChanges[id][field] = value;

            wire.call('queueChange', id, field, value);
        },
    };

    const container = document.getElementById('orders-grid');
    if (!container) return;

    const api = createGrid(container, gridOptions);

    window.__ordersGridApi = api;

    if (wire) {
        wire.call('loadOrders');
    }

    document.getElementById('btn-save-order-changes')?.addEventListener('click', () => {
        wire.call('applyChanges').then(() => {
            Object.keys(pendingChanges).forEach((k) => delete pendingChanges[k]);
        });
    });

    document.getElementById('btn-bulk-order-apply')?.addEventListener('click', () => {
        const selected = api.getSelectedRows().map((r) => r.id);
        if (selected.length === 0) return;
        wire.call('openBulkApplyModal', selected);
    });

    window.addEventListener('orders-loaded', (event) => {
        api.setGridOption('rowData', event.detail.rows);
    });

    window.addEventListener('orders-grid-refresh', (event) => {
        api.setGridOption('rowData', event.detail.rows);
        Object.keys(pendingChanges).forEach((k) => delete pendingChanges[k]);
    });
};
