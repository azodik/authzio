import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router';
import { App } from './App';
import { AppProviders } from './providers/AppProviders';

const rootElement = document.getElementById('console-root');

if (!rootElement) {
    throw new Error('Console root element #console-root was not found.');
}

createRoot(rootElement).render(
    <StrictMode>
        <AppProviders>
            <BrowserRouter basename="/console">
                <App />
            </BrowserRouter>
        </AppProviders>
    </StrictMode>,
);
