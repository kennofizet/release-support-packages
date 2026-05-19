import axios from 'axios'
import { pushCapturedLog } from '../storage'
import { redactApiErrorExtra } from '../utils/logRedaction'

export function createReleaseSupportApi(backendUrl, token) {
  if (!backendUrl) throw new Error('ReleaseSupport API: backendUrl is required')
  if (!token) throw new Error('ReleaseSupport API: token is required')

  const api = axios.create({
    baseURL: backendUrl.replace(/\/$/, ''),
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-Knf-Token': token,
    },
  })

  api.interceptors.response.use(
    (response) => response,
    (error) => {
      const response = error?.response
      pushCapturedLog({
        type: 'api_error',
        message: String(error?.message || 'API error'),
        at: new Date().toISOString(),
        extra: redactApiErrorExtra({
          method: response?.config?.method || error?.config?.method || '',
          url: response?.config?.url || error?.config?.url || '',
          status: response?.status || 0,
          data: response?.data || null,
        }),
      })
      return Promise.reject(error)
    }
  )

  return {
    bootstrap: (appVersion = null) =>
      api.get('/bootstrap', {
        params: appVersion ? { app_version: appVersion } : {},
      }),
    submitReport: (payload) => api.post('/reports', payload),
    myReports: (params = {}) => api.get('/reports/my', { params }),
    reportDetail: (reportId) => api.get(`/reports/${reportId}`),
    fetchDrawing: async (reportId, filename) => {
      const res = await api.get(`/drawings/${reportId}/${encodeURIComponent(filename)}`, {
        responseType: 'blob',
      })
      return res.data
    },

    devReports: (params = {}) => api.get('/dev/reports', { params }),
    devUpdateStatus: (reportId, status) => api.post(`/dev/reports/${reportId}/status`, { status }),
    devAddComment: (reportId, comment) => api.post(`/dev/reports/${reportId}/comments`, { comment }),
    devVersionUpdates: (params = {}) => api.get('/dev/version-updates', { params }),
    devCreateVersionUpdate: (payload) => api.post('/dev/version-updates', payload),
    devUpdateVersionUpdate: (id, payload) => api.put(`/dev/version-updates/${id}`, payload),
    devMetrics: (days = 30) => api.get('/dev/metrics', { params: { days } }),
  }
}
