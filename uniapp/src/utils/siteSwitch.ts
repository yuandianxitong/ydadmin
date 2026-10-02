/** 与服务端开关同一套关闭值：0 / false / no / off。 */
export function isSwitchOff(value: unknown): boolean {
  if (value === false || value === 0) return true
  const text = String(value ?? '').trim().toLowerCase()
  return text === '0' || text === 'false' || text === 'no' || text === 'off'
}
