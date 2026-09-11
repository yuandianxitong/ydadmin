export const isMobile = (value: string): boolean => /^1[3-9]\d{9}$/.test(value)

export const isPassword = (value: string): boolean => value.length >= 6 && value.length <= 20

export const isVerifyCode = (value: string): boolean => /^\d{4,6}$/.test(value)

export const isEmpty = (value: any): boolean => {
  return value === null || value === undefined || value === ''
}
