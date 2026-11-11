import assert from 'node:assert/strict'
import fs from 'node:fs'
const { createTooltipVisibility } = await import(`data:text/javascript;base64,${Buffer.from(fs.readFileSync(new URL('../src/services/tooltipVisibility.js', import.meta.url))).toString('base64')}`)
let now = 0, nextId = 0, visible = false
const timers = new Map()
const controller = createTooltipVisibility({
 setVisible: value => { visible = value }, isVisible: () => visible,
 schedule: (fn, delay) => { const id = ++nextId; timers.set(id, { fn, at: now + delay }); return id },
 cancel: id => timers.delete(id),
})
function tick(ms) { now += ms; for (const [id, timer] of [...timers]) if (timer.at <= now) { timers.delete(id); timer.fn() } }
controller.enter(); tick(399); assert.equal(visible, false)
controller.leave(); tick(500); assert.equal(visible, false, 'Passing over a tooltip never opens it later')
controller.enter(); tick(400); assert.equal(visible, true)
controller.leave(); tick(99); assert.equal(visible, true)
controller.enter(); tick(100); assert.equal(visible, true, 'Re-entry cancels hiding without flicker')
controller.leave(); tick(100); assert.equal(visible, false)
controller.enter(); controller.focus(); assert.equal(visible, true, 'Keyboard focus bypasses hover delay')
controller.leave(); tick(500); assert.equal(visible, true, 'Pointer exit cannot dismiss a focused hint')
controller.blur(); assert.equal(visible, false)
controller.enter(); controller.toggle(); assert.equal(visible, true, 'Explicit activation is immediate')
controller.toggle(); tick(500); assert.equal(visible, false, 'Cancelled hover cannot reopen a dismissed hint')
controller.enter(); controller.dispose(); tick(500); assert.equal(visible, false)
assert.equal(timers.size, 0, 'Unmount leaves no delayed show/hide callbacks')
console.log('Tooltip timing passed: delayed hover, cancelled fly-by, stable re-entry, immediate focus/activation and disposal.')

const tooltipSource=fs.readFileSync(new URL('../src/components/TableTooltip.vue',import.meta.url),'utf8')
const tooltipScript=tooltipSource.split('<script>')[1].split('</script>')[0].replace(/^import .*$/gm,'').replace('export default','return')
const tooltip=new Function('createTooltipVisibility',tooltipScript)(createTooltipVisibility)
let stops=0,prevents=0,toggles=0
const event={stopPropagation(){stops++},preventDefault(){prevents++}}
const hint={passive:false,visibility:{toggle(){toggles++}}}
tooltip.methods.toggleTooltip.call(hint,event)
assert.deepEqual([stops,prevents,toggles],[1,1,1],'Normal table tooltips still handle explicit activation')
hint.passive=true
tooltip.methods.toggleTooltip.call(hint,event)
assert.deepEqual([stops,prevents,toggles],[1,1,1],'Passive card hints allow amount clicks and Enter to reach the surrounding report link')
console.log('Tooltip activation passed: ordinary hints retain their controls; passive hints do not intercept links.')
