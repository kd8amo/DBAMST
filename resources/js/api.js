import axios from 'axios'

const api = axios.create({
    baseURL: '/api',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    },
})

// Redirect to login on 401
api.interceptors.response.use(
    response => response,
    error => {
        if (error.response?.status === 401) {
            localStorage.removeItem('tsm_token')
            localStorage.removeItem('tsm_user')
            window.location.href = '/login'
        }
        return Promise.reject(error)
    }
)

export default api
