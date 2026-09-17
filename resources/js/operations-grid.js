import { createGrid, ModuleRegistry, AllCommunityModule, themeQuartz } from 'ag-grid-community';
import './resource-drawer';
ModuleRegistry.registerModules([AllCommunityModule]);
let cleanup = () => {};
let dirty=false;
const textCell = p => {
 const el=document.createElement('span');el.textContent=p.value??'—';return el;
};
const safeUrl = v => {try {const u=new URL(v);return ['http:','https:'].includes(u.protocol)?u.href:null;}catch{return null;}};
function init() {
 cleanup();
 const el=document.querySelector('[data-resource-grid]');
 if(!el)return;
 const wire=window.Livewire.find(el.closest('[wire\\:id]').getAttribute('wire:id'));
 const product=el.dataset.resourceGrid==='product';
 const theme=()=>themeQuartz.withParams({accentColor:'#147d64',backgroundColor:getComputedStyle(document.documentElement).getPropertyValue('--surface').trim(),foregroundColor:getComputedStyle(document.documentElement).getPropertyValue('--text').trim(),browserColorScheme:document.documentElement.dataset.theme});
 const columns=product?[
  {field:'image',headerName:'Imagem',width:90,cellRenderer:p=>{const src=safeUrl(p.value);if(!src)return textCell({value:'—'});const img=document.createElement('img');img.src=src;img.alt='';img.className='product-image';img.loading='lazy';return img;}},
  {field:'name',headerName:'Nome',flex:2,minWidth:220},{field:'sku',headerName:'SKU',minWidth:160},
  {field:'url',headerName:'URL',cellRenderer:p=>{const u=safeUrl(p.value);if(!u)return textCell({value:'—'});const a=document.createElement('a');a.href=u;a.target='_blank';a.rel='noopener noreferrer';a.textContent='Abrir na loja';a.addEventListener('click',e=>e.stopPropagation());return a;}},
  {field:'price',headerName:'Preço'},{field:'weight',headerName:'Peso'},{field:'status',headerName:'Status'}
 ]:[{field:'number',headerName:'Pedido'},{field:'created_at',headerName:'Data'},{field:'customer',headerName:'Cliente',flex:1,minWidth:170},{field:'customer_email',headerName:'E-mail',minWidth:220},{field:'status',headerName:'Status'},{field:'total',headerName:'Total'},{field:'payment_method',headerName:'Pagamento'}];
 let opening=false;
 const open=async e=>{
  if(opening || (dirty&&!confirm('Descartar alterações não salvas?')))return;
  opening=true;
  window.dispatchEvent(new CustomEvent('resource-opening',{detail:{title:e.data.name??`Pedido ${e.data.number??e.data.id}`}}));
  try { await wire.call('openDetail',String(product?e.data.key:e.data.id)); }
  finally { opening=false;window.dispatchEvent(new CustomEvent('resource-opened')); }
 };
 const api=createGrid(el,{theme:theme(),columnDefs:columns,rowData:[],getRowId:p=>String(product?p.data.key:p.data.id),loading:true,rowHeight:54,defaultColDef:{resizable:true,sortable:false,width:130,cellRenderer:textCell},rowSelection:{mode:'multiRow',enableClickSelection:false},onRowClicked:open,overlayNoRowsTemplate:'<span>Nenhum resultado nesta página.</span>'});
 const updated=e=>{const row=e.detail.row;if(row&&api.getRowNode(String(product?row.key:row.id)))api.applyTransaction({update:[row]});};
 const rows=e=>{api.setGridOption('loading',false);api.setGridOption('rowData',e.detail.rows??[]);};
 const themed=()=>api.setGridOption('theme',theme());
 const bulk=e=>{if(e.target.closest('[data-bulk-select]')){const ids=api.getSelectedRows().map(r=>String(product?r.key:r.id));if(ids.length)wire.call('openBulkApplyModal',ids);else alert('Selecione ao menos uma linha.');}};
 window.addEventListener('resource-row-updated',updated);window.addEventListener('resource-rows',rows);window.addEventListener('theme-changed',themed);document.addEventListener('click',bulk);
 wire.call('loadRows').then(r=>{if(!api.isDestroyed()){api.setGridOption('loading',false);api.setGridOption('rowData',r??[]);}});
 cleanup=()=>{window.removeEventListener('resource-row-updated',updated);window.removeEventListener('resource-rows',rows);window.removeEventListener('theme-changed',themed);document.removeEventListener('click',bulk);api.destroy();cleanup=()=>{};};
}
window.addEventListener('resource-dirty',e=>{dirty=Boolean(e.detail.dirty);});
window.addEventListener('edits-saved',()=>{dirty=false;});
window.addEventListener('beforeunload',e=>{if(dirty){e.preventDefault();e.returnValue='';}});
document.addEventListener('livewire:navigate',e=>{if(dirty&&!confirm('Descartar alterações não salvas?'))e.preventDefault();});
document.addEventListener('livewire:navigating',()=>{cleanup();dirty=false;});
document.addEventListener('livewire:navigated',init);
