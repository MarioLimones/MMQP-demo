<fieldset><legend>Fondo</legend>
    <span class="arqueo-color-label">Color <button type="button" id="cell-bg-color" aria-label="Elegir color de fondo" aria-expanded="false" aria-controls="arqueo-color-picker"><span data-color-swatch aria-hidden="true"></span></button></span>
    <button type="button" onclick="cmd('bg-none')" class="arqueo-button">Sin fondo</button>
    <div id="arqueo-color-picker" class="arqueo-color-picker" popover="auto" role="dialog" aria-label="Color de fondo">
        <div class="arqueo-color-plane" data-color-plane aria-hidden="true"><span data-color-cursor></span></div>
        <label class="sr-only" for="cell-color-hue">Tono</label><input id="cell-color-hue" type="range" min="0" max="360" value="220" class="arqueo-color-hue">
        <div class="sr-only"><label for="cell-color-saturation">Saturación</label><input id="cell-color-saturation" type="range" min="0" max="100" value="3"><label for="cell-color-brightness">Brillo</label><input id="cell-color-brightness" type="range" min="0" max="100" value="92"></div>
        <div class="arqueo-color-code"><label for="cell-color-hex">HEX</label><input id="cell-color-hex" type="text" value="#E5E7EB" maxlength="7" spellcheck="false" autocomplete="off" autocapitalize="characters" aria-describedby="cell-color-help cell-color-error"><button type="button" id="cell-color-apply" class="arqueo-button">Aplicar</button></div>
        <p id="cell-color-help">Copia o pega el código para repetir un color.</p>
        <p id="cell-color-error" role="alert" hidden>Escribe un código como #E5E7EB.</p>
    </div>
</fieldset>
