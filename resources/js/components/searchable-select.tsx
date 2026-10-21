import { useEffect, useState } from 'react';

export type SearchOption = { id: number; label: string; description?: string | null };
type Props = {
    label: string;
    placeholder: string;
    searchUrl: string;
    selected: SearchOption | null;
    onSelect: (option: SearchOption | null) => void;
    error?: string;
    disabled?: boolean;
};

export function SearchableSelect({ label, placeholder, searchUrl, selected, onSelect, error, disabled = false }: Props) {
    const [term, setTerm] = useState('');
    const [results, setResults] = useState<SearchOption[]>([]);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        const query = term.trim();
        if (disabled || query.length < 2) { setResults([]); setLoading(false); return; }
        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            setLoading(true);
            fetch(`${searchUrl}?q=${encodeURIComponent(query)}`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, signal: controller.signal })
                .then(response => response.ok ? response.json() : Promise.reject(new Error('Échec de la recherche')))
                .then((options: SearchOption[]) => setResults(options))
                .catch(error => { if (error.name !== 'AbortError') setResults([]); })
                .finally(() => { if (!controller.signal.aborted) setLoading(false); });
        }, 250);
        return () => { window.clearTimeout(timer); controller.abort(); };
    }, [term, searchUrl, disabled]);

    return <div className="relative"><label className="block text-sm">{label}<input disabled={disabled} value={selected ? selected.label : term} onChange={event => { onSelect(null); setTerm(event.target.value); }} placeholder={placeholder} autoComplete="off" className="mt-1 w-full rounded-md border bg-background px-3 py-2" />{error ? <small className="text-destructive">{error}</small> : null}</label>
        {!selected && term.trim().length >= 2 ? <div className="absolute z-20 mt-1 max-h-60 w-full overflow-y-auto rounded-md border bg-popover shadow-md">{loading ? <p className="p-3 text-sm text-muted-foreground">Recherche…</p> : results.length ? results.map(option => <button type="button" key={option.id} onClick={() => { onSelect(option); setTerm(''); setResults([]); }} className="block w-full border-b px-3 py-2 text-left text-sm last:border-0 hover:bg-accent"><span className="block">{option.label}</span>{option.description ? <span className="block text-xs text-muted-foreground">{option.description}</span> : null}</button>) : <p className="p-3 text-sm text-muted-foreground">Aucun résultat. Vérifiez l’orthographe ou le numéro.</p>}</div> : null}
        {selected ? <button type="button" onClick={() => { onSelect(null); setTerm(''); }} className="mt-1 text-xs text-primary underline">Changer la sélection</button> : term.trim().length < 2 ? <small className="mt-1 block text-muted-foreground">Saisissez au moins 2 caractères.</small> : null}
    </div>;
}
