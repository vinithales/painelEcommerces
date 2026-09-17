import { createGrid, ModuleRegistry, AllCommunityModule, themeQuartz } from 'ag-grid-community';

ModuleRegistry.registerModules([AllCommunityModule]);

// ── Cell Renderers ────────────────────────────────────────────

function statusRenderer(params) {
    const map = {
        publish:  { label: 'Publicado', cls: 'status-badge--publish' },
        draft:    { label: 'Rascunho', cls: 'status-badge--draft' },
        enabled:  { label: 'Habilitado', cls: 'status-badge--enabled' },
        disabled: { label: 'Desabilitado', cls: 'status-badge--disabled' },
    };
    const v = params.value ?? '';
    const cfg = map[v] ?? { label: v || '—', cls: 'status-badge--unknown' };
    return `<span class="status-badge ${cfg.cls}">${cfg.label}</span>`;
}

function priceRenderer(params) {
    const v = parseFloat(params.value);
    if (isNaN(v)) return '—';
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v);
}

function stockRenderer(params) {
    const v = params.value;
    if (v === null || v === undefined || v === '') return '<span class="stock-unknown">—</span>';
    const n = Number(v);
    const cls = n <= 0 ? 'stock-zero' : n <= 5 ? 'stock-low' : 'stock-ok';
    return `<span class="${cls}">${n}</span>`;
}

// ── External Filter State ─────────────────────────────────────

const filterState = {
    sku: '',
    skuPrefix: '',
    status: '',
    dateFrom: '',
    dateTo: '',
};

function isExternalFilterPresent() {
    return filterState.sku !== ''
        || filterState.skuPrefix !== ''
        || filterState.status !== ''
        || filterState.dateFrom !== ''
        || filterState.dateTo !== '';
}

function doesExternalFilterPass(node) {
    const p = node.data;
    if (!p) return true;

    if (filterState.sku && (p.sku ?? '').toLowerCase() !== filterState.sku.toLowerCase()) return false;
    if (filterState.skuPrefix && !(p.sku ?? '').toLowerCase().startsWith(filterState.skuPrefix.toLowerCase())) return false;
    if (filterState.status && (p.status ?? '') !== filterState.status) return false;
    if (filterState.dateFrom && (p.created_at ?? '') < filterState.dateFrom) return false;
    if (filterState.dateTo && (p.created_at ?? '') > filterState.dateTo) return false;

    return true;
}

// ── Grid Init ─────────────────────────────────────────────────

window.initProductsGrid = function (wireId) {
    const wire = window.Livewire.find(wireId);
    const pendingChanges = {};

    const columnDefs = [
        {
            headerName: '',
            width: 44,
            pinned: 'left',
            suppressSizeToFit: true,
            headerCheckboxSelection: true,
            checkboxSelection: true,
            editable: false,
            sortable: false,
            filter: false,
        },
        {
            field: 'id',
            headerName: 'ID',
            width: 80,
            editable: false,
            pinned: 'left',
        },
        {
            field: 'sku',
            headerName: 'SKU',
            editable: true,
            minWidth: 140,
            flex: 1,
            pinned: 'left',
        },
        {
            field: 'name',
            headerName: 'Name',
            editable: true,
            minWidth: 200,
            flex: 2,
        },
        {
            field: 'price',
            headerName: 'Price',
            editable: true,
            width: 120,
            cellRenderer: priceRenderer,
            cellStyle: { textAlign: 'right' },
        },
        {
            field: 'stock_quantity',
            headerName: 'Stock',
            editable: true,
            width: 90,
            cellRenderer: stockRenderer,
            cellStyle: { textAlign: 'right' },
        },
        {
            field: 'status',
            headerName: 'Status',
            editable: true,
            width: 120,
            cellRenderer: statusRenderer,
        },
        {
            field: 'created_at',
            headerName: 'Created',
            editable: false,
            width: 160,
            valueFormatter: (p) => p.value ? new Date(p.value).toLocaleDateString('pt-BR') : '—',
        },
    ];

    const gridOptions = {
        columnDefs,
        rowData: [],
        rowHeight: 44,
        headerHeight: 40,
        theme: themeQuartz,
        rowSelection: {
            mode: 'multiRow',
            checkboxes: true,
            headerCheckbox: true,
            enableClickSelection: false,
        },
        pagination: true,
        paginationPageSize: 50,
        paginationPageSizeSelector: [25, 50, 100, 250],
        isExternalFilterPresent,
        doesExternalFilterPass,
        defaultColDef: {
            sortable: true,
            resizable: true,
            suppressHeaderMenuButton: false,
        },
        onCellValueChanged(event) {
            const id = String(event.data.id);
            const field = event.colDef.field;
            const value = event.newValue;

            if (!pendingChanges[id]) pendingChanges[id] = {};
            pendingChanges[id][field] = value;

            wire.call('queueChange', id, field, value);
        },
        overlayLoadingTemplate: '<div class="grid-overlay"><span class="cache-progress__spinner"></span> Carregando produtos...</div>',
        overlayNoRowsTemplate: '<div class="grid-overlay grid-overlay--empty">Nenhum produto corresponde aos filtros atuais.</div>',
    };

    const container = document.getElementById('products-grid');
    if (!container) return;

    const api = createGrid(container, gridOptions);
    api.showLoadingOverlay();

    window.__productsGridApi = api;
    window.__productsGridPending = pendingChanges;

    // ── Filter inputs (all client-side) ─────────────────────

    function bindFilter(id, stateKey) {
        const el = document.getElementById(id);
        if (!el) return;
        const evt = el.tagName === 'SELECT' ? 'change' : 'input';
        el.addEventListener(evt, () => {
            filterState[stateKey] = el.value.trim();
            api.onFilterChanged();
        });
    }

    bindFilter('filter-sku', 'sku');
    bindFilter('filter-sku-prefix', 'skuPrefix');
    bindFilter('filter-status', 'status');
    bindFilter('filter-date-from', 'dateFrom');
    bindFilter('filter-date-to', 'dateTo');

    document.getElementById('btn-clear-filters')?.addEventListener('click', () => {
        ['filter-sku', 'filter-sku-prefix', 'filter-date-from', 'filter-date-to'].forEach((id) => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        const sel = document.getElementById('filter-status');
        if (sel) sel.value = '';

        filterState.sku = '';
        filterState.skuPrefix = '';
        filterState.status = '';
        filterState.dateFrom = '';
        filterState.dateTo = '';

        api.onFilterChanged();
    });

    // ── Action buttons ───────────────────────────────────────

    document.getElementById('btn-save-changes')?.addEventListener('click', () => {
        wire.call('applyChanges').then(() => {
            Object.keys(pendingChanges).forEach((k) => delete pendingChanges[k]);
        });
    });

    document.getElementById('btn-bulk-apply')?.addEventListener('click', () => {
        const selected = api.getSelectedRows().map((r) => String(r.id));
        if (selected.length === 0) return;
        wire.call('openBulkApplyModal', selected);
    });

    document.getElementById('btn-preview')?.addEventListener('click', () => {
        wire.call('previewChanges');
    });

    // ── Livewire events ──────────────────────────────────────

    window.addEventListener('products-ready', (event) => {
        api.hideOverlay();
        api.setGridOption('rowData', event.detail.rows);
    });

    window.addEventListener('products-grid-refresh', (event) => {
        api.setGridOption('rowData', event.detail.rows ?? []);
        api.showLoadingOverlay();
        Object.keys(pendingChanges).forEach((k) => delete pendingChanges[k]);
    });
};
