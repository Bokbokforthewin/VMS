import { useEffect, useState } from 'react'
import api from './lib/api'

function App() {
  const [msg, setMsg] = useState('Loading...')

  useEffect(() => {
    api.get('/test')
      .then(res => setMsg(res.data.message))
      .catch(() => setMsg('Error connecting to API'))
  }, [])

  return (
    <div style={{ padding: 40 }}>
      <h1>React + Laravel</h1>
      <p>API says: <strong>{msg}</strong></p>
    </div>
  )
}

export default App