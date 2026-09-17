export function hasDraftChanges(draft = {}, baseline = {}) {
    const value = v => v === null || v === undefined ? '' : typeof v === 'boolean' ? String(Number(v)) : String(v);
    return [...new Set([...Object.keys(draft), ...Object.keys(baseline)])]
        .some(key => value(draft[key]) !== value(baseline[key]));
}

window.resourceDrawer = () => ({
    opening: false,
    openingTitle: '',
    get dirty() { return hasDraftChanges(this.$wire.draft, this.$wire.initialDraft); },
    confirmDiscard() { return !this.dirty || window.confirm('Descartar alterações não salvas?'); },
    close() { if (this.confirmDiscard()) this.$wire.closeDetail(); },
    refresh() { if (this.confirmDiscard()) this.$wire.openDetail(this.$wire.selectedKey); },
    discard() { if (this.confirmDiscard()) this.$wire.discardDetail(); },
});
