export function captureErrors(page, observed) {
  page.on('console', message => {
    if (message.type() !== 'error') return;
    observed.console_error_count++;
    if (observed.console_errors.length < 100) observed.console_errors.push({ message: message.text().slice(0, 8000), ...message.location() });
  });
  page.on('pageerror', error => {
    observed.page_error_count++;
    if (observed.page_errors.length < 100) observed.page_errors.push({ message: error.message.slice(0, 8000), stack: error.stack?.slice(0, 16000) });
  });
}
