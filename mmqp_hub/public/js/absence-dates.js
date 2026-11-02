(function (root) {
    const days = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
    const normalize = value => String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    root.AbsenceDates = {
        isoDate(text, week, dayName) {
            if (/^\d{4}-\d{2}-\d{2}$/.test(text || '')) return text;
            const match = /^(\d{4})-W(\d{2})$/.exec(week || '');
            const offset = days.indexOf(normalize(dayName));
            if (!match || offset < 0) return null;
            const date = new Date(Date.UTC(Number(match[1]), 0, 4));
            date.setUTCDate(date.getUTCDate() - (date.getUTCDay() || 7) + 1 + (Number(match[2]) - 1) * 7 + offset);
            return date.toISOString().slice(0, 10);
        },
        contains(dates, text, week, dayName) {
            const iso = this.isoDate(text, week, dayName);
            return !!iso && Array.isArray(dates) && dates.includes(iso);
        }
    };
})(globalThis);
