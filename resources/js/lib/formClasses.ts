/**
 * Estilo para <select> y <textarea> nativos, igual al del componente Input.
 * Se usan nativos porque en el celular abren el selector del sistema, que es
 * lo más cómodo para el dedo.
 */
export const fieldClass =
    'border-input bg-card dark:bg-input/30 placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:border-destructive w-full rounded-md border px-3 text-base shadow-xs outline-none transition-[color,box-shadow] focus-visible:ring-[3px] disabled:opacity-50 md:text-sm';

export const selectClass = `${fieldClass} h-11 appearance-none bg-[length:16px] bg-[right_0.75rem_center] bg-no-repeat pr-9 [background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%238a968f' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E")]`;

export const textareaClass = `${fieldClass} min-h-24 py-2.5 leading-relaxed`;
