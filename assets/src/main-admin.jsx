import React from 'react';
import { createRoot } from 'react-dom/client';

function AdminApp() {
  return <p>Printer Builder Configurator admin app scaffold is ready.</p>;
}

const mountNode = document.getElementById('pbc-admin-app');
if (mountNode) {
  createRoot(mountNode).render(<AdminApp />);
}
