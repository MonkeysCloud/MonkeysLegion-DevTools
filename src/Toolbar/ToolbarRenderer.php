<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Toolbar;

use MonkeysLegion\DevTools\Profiler\Profile;

/**
 * MonkeysLegion Framework — DevTools Package
 *
 * Renders the complete debug toolbar HTML with embedded CSS/JS.
 * Self-contained — no external dependencies needed.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class ToolbarRenderer
{
    /** @var list<PanelInterface> */
    private array $panels = [];

    private bool $sorted = false;

    /**
     * Number of registered panels.
     */
    public int $panelCount {
        get => count($this->panels);
    }

    /**
     * Register a toolbar panel.
     */
    public function addPanel(PanelInterface $panel): void
    {
        $this->panels[] = $panel;
        $this->sorted = false;
    }

    /**
     * Render the complete debug toolbar HTML.
     */
    public function render(Profile $profile): string
    {
        $this->ensureSorted();

        $tabs = '';
        $panelContents = '';

        foreach ($this->panels as $i => $panel) {
            $id = $panel->id();
            $severity = $panel->badgeSeverity($profile);
            $badge = $panel->badge($profile);
            $active = $i === 0 ? ' ml-dt-tab--active' : '';

            $tabs .= "<button class=\"ml-dt-tab{$active}\" "
                . "data-panel=\"{$id}\" "
                . "data-severity=\"{$severity}\">"
                . "<span class=\"ml-dt-tab-icon\">{$panel->icon()}</span>"
                . "<span class=\"ml-dt-tab-label\">{$panel->label()}</span>"
                . "<span class=\"ml-dt-tab-badge ml-dt-sev--{$severity}\">{$badge}</span>"
                . '</button>';

            $display = $i === 0 ? '' : ' style="display:none"';
            $panelContents .= "<div class=\"ml-dt-panel\" id=\"ml-dt-panel-{$id}\"{$display}>"
                . $panel->render($profile)
                . '</div>';
        }

        $statusEmoji = $profile->statusBadge;
        $statusClass = $profile->isError ? 'ml-dt-bar--error' : ($profile->isSlow ? 'ml-dt-bar--warning' : 'ml-dt-bar--ok');

        return <<<HTML
<!-- MonkeysLegion DevTools v2 -->
<div id="ml-devtools" class="ml-dt-root" data-profile-id="{$profile->id}">
  <div class="ml-dt-bar {$statusClass}">
    <div class="ml-dt-bar-left">
      <button class="ml-dt-logo" id="ml-dt-toggle" title="Toggle DevTools">
        <span class="ml-dt-logo-icon">🐒</span>
        <span class="ml-dt-logo-text">ML</span>
      </button>
      <span class="ml-dt-bar-status">{$statusEmoji} {$profile->statusCode}</span>
      <span class="ml-dt-bar-method">{$profile->method}</span>
      <span class="ml-dt-bar-uri" title="{$profile->uri}">{$this->truncate($profile->uri, 60)}</span>
    </div>
    <div class="ml-dt-bar-right">
      <span class="ml-dt-bar-duration">{$profile->durationFormatted}</span>
      <span class="ml-dt-bar-memory">{$profile->memoryPeakFormatted}</span>
      <button class="ml-dt-close" id="ml-dt-close" title="Close toolbar">✕</button>
    </div>
  </div>
  <div class="ml-dt-body" id="ml-dt-body" style="display:none">
    <div class="ml-dt-tabs">{$tabs}</div>
    <div class="ml-dt-panels">{$panelContents}</div>
  </div>
</div>
<style>{$this->renderCSS()}</style>
<script>{$this->renderJS()}</script>
<!-- /MonkeysLegion DevTools -->
HTML;
    }

    /**
     * Render the self-contained CSS.
     */
    private function renderCSS(): string
    {
        return <<<'CSS'
.ml-dt-root{--ml-bg:#1a1b26;--ml-bg2:#24283b;--ml-bg3:#2f334d;--ml-fg:#a9b1d6;--ml-fg2:#787c99;--ml-accent:#7aa2f7;--ml-ok:#9ece6a;--ml-warn:#e0af68;--ml-err:#f7768e;--ml-border:#3b3f5c;--ml-font:'SF Mono',ui-monospace,'Cascadia Code',Menlo,monospace;--ml-radius:8px;position:fixed;bottom:0;left:0;right:0;z-index:999999;font-family:var(--ml-font);font-size:12px;line-height:1.5;color:var(--ml-fg);-webkit-font-smoothing:antialiased}
.ml-dt-bar{display:flex;justify-content:space-between;align-items:center;padding:0 12px;height:36px;background:var(--ml-bg);border-top:2px solid var(--ml-border);cursor:pointer;transition:border-color .2s}
.ml-dt-bar--ok{border-top-color:var(--ml-ok)}.ml-dt-bar--warning{border-top-color:var(--ml-warn)}.ml-dt-bar--error{border-top-color:var(--ml-err)}
.ml-dt-bar-left,.ml-dt-bar-right{display:flex;align-items:center;gap:12px}
.ml-dt-logo{display:flex;align-items:center;gap:4px;background:none;border:none;color:var(--ml-accent);cursor:pointer;font-size:13px;padding:2px 6px;border-radius:4px;transition:background .15s}
.ml-dt-logo:hover{background:var(--ml-bg3)}.ml-dt-logo-icon{font-size:16px}.ml-dt-logo-text{font-weight:700;letter-spacing:1px}
.ml-dt-bar-status{font-weight:600}.ml-dt-bar-method{color:var(--ml-accent);font-weight:600}
.ml-dt-bar-uri{color:var(--ml-fg2);max-width:400px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ml-dt-bar-duration{color:var(--ml-ok);font-weight:600}.ml-dt-bar-memory{color:var(--ml-fg2)}
.ml-dt-close{background:none;border:none;color:var(--ml-fg2);cursor:pointer;font-size:14px;padding:4px 6px;border-radius:4px;transition:all .15s}
.ml-dt-close:hover{color:var(--ml-err);background:var(--ml-bg3)}
.ml-dt-body{background:var(--ml-bg);border-top:1px solid var(--ml-border);max-height:50vh;overflow:hidden;display:flex;flex-direction:column;animation:ml-dt-slideUp .2s ease}
@keyframes ml-dt-slideUp{from{max-height:0;opacity:0}to{max-height:50vh;opacity:1}}
.ml-dt-tabs{display:flex;gap:2px;padding:6px 12px 0;border-bottom:1px solid var(--ml-border);overflow-x:auto;flex-shrink:0}
.ml-dt-tab{display:flex;align-items:center;gap:6px;padding:6px 12px;background:none;border:none;border-bottom:2px solid transparent;color:var(--ml-fg2);cursor:pointer;font-size:11px;font-family:var(--ml-font);white-space:nowrap;transition:all .15s}
.ml-dt-tab:hover{color:var(--ml-fg);background:var(--ml-bg2)}.ml-dt-tab--active{color:var(--ml-fg);border-bottom-color:var(--ml-accent)}
.ml-dt-tab-icon{font-size:14px}.ml-dt-tab-badge{font-size:10px;padding:1px 6px;border-radius:10px;font-weight:600}
.ml-dt-sev--ok{background:rgba(158,206,106,.15);color:var(--ml-ok)}.ml-dt-sev--warning{background:rgba(224,175,104,.15);color:var(--ml-warn)}.ml-dt-sev--error{background:rgba(247,118,142,.15);color:var(--ml-err)}
.ml-dt-panels{overflow-y:auto;flex:1;padding:12px 16px}
.ml-dt-panel{min-height:100px}
.ml-dt-section{margin-bottom:16px}.ml-dt-section-heading{font-size:13px;font-weight:700;color:var(--ml-accent);margin:0 0 8px;padding-bottom:4px;border-bottom:1px solid var(--ml-border)}
.ml-dt-section-title{font-size:11px;font-weight:600;color:var(--ml-fg2);text-transform:uppercase;letter-spacing:.5px;margin:12px 0 6px}
.ml-dt-table{width:100%;border-collapse:collapse}.ml-dt-table td{padding:3px 8px;border-bottom:1px solid var(--ml-border)}
.ml-dt-key{color:var(--ml-fg2);width:140px;font-weight:500}.ml-dt-value{color:var(--ml-fg);word-break:break-all}
.ml-dt-grid-wrap{overflow-x:auto}.ml-dt-grid{width:100%;border-collapse:collapse;font-size:11px}
.ml-dt-grid th{text-align:left;padding:4px 8px;background:var(--ml-bg2);color:var(--ml-fg2);font-weight:600;border-bottom:1px solid var(--ml-border);position:sticky;top:0}
.ml-dt-grid td{padding:3px 8px;border-bottom:1px solid var(--ml-border);max-width:400px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ml-dt-grid tr:hover td{background:var(--ml-bg3)}
.ml-dt-badges,.ml-dt-metrics{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px}
.ml-dt-badge{display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:6px;font-size:11px;background:var(--ml-bg2);border:1px solid var(--ml-border)}
.ml-dt-badge-label{color:var(--ml-fg2)}.ml-dt-badge-value{font-weight:700;color:var(--ml-fg)}
.ml-dt-badge--warning{border-color:var(--ml-warn)}.ml-dt-badge--error{border-color:var(--ml-err)}
.ml-dt-status{display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;margin:2px 0}
.ml-dt-status--ok{color:var(--ml-ok)}.ml-dt-status--error{color:var(--ml-err);background:rgba(247,118,142,.1)}
.ml-dt-null{color:var(--ml-fg2);font-style:italic}.ml-dt-bool--true{color:var(--ml-ok)}.ml-dt-bool--false{color:var(--ml-err)}
.ml-dt-empty{color:var(--ml-fg2);font-style:italic;text-align:center;padding:20px}
.ml-dt-exception{margin-bottom:16px;padding:12px;background:var(--ml-bg2);border-radius:var(--ml-radius);border-left:3px solid var(--ml-err)}
.ml-dt-exception-class{font-size:13px;color:var(--ml-err);font-weight:700;margin:0 0 4px}
.ml-dt-exception-message{color:var(--ml-fg);margin:0 0 4px;font-size:12px}
.ml-dt-exception-location{color:var(--ml-fg2);font-size:11px;margin:0 0 8px}
CSS;
    }

    /**
     * Render the self-contained JS.
     */
    private function renderJS(): string
    {
        return <<<'JS'
(function(){
  const root=document.getElementById('ml-devtools');
  if(!root)return;
  const body=document.getElementById('ml-dt-body');
  const toggle=document.getElementById('ml-dt-toggle');
  const close=document.getElementById('ml-dt-close');
  const bar=root.querySelector('.ml-dt-bar');
  let open=false;

  function toggleBody(){
    open=!open;
    body.style.display=open?'flex':'none';
  }

  bar.addEventListener('click',function(e){
    if(e.target===close||close.contains(e.target))return;
    toggleBody();
  });

  close.addEventListener('click',function(e){
    e.stopPropagation();
    root.style.display='none';
  });

  // Tab switching
  root.querySelectorAll('.ml-dt-tab').forEach(function(tab){
    tab.addEventListener('click',function(e){
      e.stopPropagation();
      if(!open)toggleBody();
      root.querySelectorAll('.ml-dt-tab').forEach(t=>t.classList.remove('ml-dt-tab--active'));
      tab.classList.add('ml-dt-tab--active');
      root.querySelectorAll('.ml-dt-panel').forEach(p=>p.style.display='none');
      const panel=document.getElementById('ml-dt-panel-'+tab.dataset.panel);
      if(panel)panel.style.display='block';
    });
  });

  // Keyboard shortcut: Ctrl+Shift+D
  document.addEventListener('keydown',function(e){
    if(e.ctrlKey&&e.shiftKey&&e.key==='D'){
      e.preventDefault();
      if(root.style.display==='none'){root.style.display='block';open=false;}
      toggleBody();
    }
  });
})();
JS;
    }

    private function truncate(string $text, int $max): string
    {
        return strlen($text) > $max
            ? substr($text, 0, $max - 3) . '...'
            : $text;
    }

    private function ensureSorted(): void
    {
        if ($this->sorted) {
            return;
        }

        usort(
            $this->panels,
            static fn(PanelInterface $a, PanelInterface $b): int => $b->priority() <=> $a->priority(),
        );

        $this->sorted = true;
    }
}
