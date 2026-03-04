import React from 'react';
import { createRoot } from 'react-dom/client';

function PublicApp() {
  return <p>Printer Builder Configurator frontend app scaffold is ready.</p>;
}

const mountNode = document.getElementById('pbc-public-app');
if (mountNode) {
  createRoot(mountNode).render(<PublicApp />);
}
