import { http } from '@/utils/request'

export interface AgreementItem {
  id: number
  title: string
  code: string
  content: string
}

export const agreementApi = {
  getByCode: (code: string) =>
    http.get<AgreementItem>(`/api/agreement/${code}`),
}
