import './bootstrap';
import './operations-grid';
document.addEventListener('click', e => {
 if(e.target.closest('[data-theme-toggle]')) {
  const theme=document.documentElement.dataset.theme==='dark'?'light':'dark';
  document.documentElement.dataset.theme=theme; localStorage.setItem('bring-theme',theme);
  window.dispatchEvent(new Event('theme-changed'));
 }
 if(e.target.closest('[data-sidebar-toggle]')) {
  const state=document.documentElement.dataset.sidebar==='closed'?'open':'closed';
  document.documentElement.dataset.sidebar=state; localStorage.setItem('bring-sidebar',state);
 }
});
matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e=>{
 if(!localStorage.getItem('bring-theme')) { document.documentElement.dataset.theme=e.matches?'dark':'light'; window.dispatchEvent(new Event('theme-changed')); }
});
