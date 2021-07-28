const https = require('https')
const axios = require('axios')
const { createHash } = require('crypto')
const { v4: uuidv4 } = require('uuid')
const fs = require('fs')

class CwaAdapter {
  /**
  * Constructs an CWA-backend interface that can be used to submit test results to the CWA Backend.
  *
  * @param {Object} connectionData
  * @param {string} connectionData.baseURL The CWA url that has been provided
  * @param {string} connectionData.certPath Relative or absolute path to the crt file
  * @param {string} connectionData.keyPath Relative or absolute path to the key file
  * @param {string} connectionData.[passphrase] Passphrase for the certificate key file
  * @return void
  */
  constructor ({ baseURL, certPath, keyPath, passphrase }) {
    if (!baseURL || !certPath || !keyPath) {
      throw new Error('CwaAdapter requires baseURL, certPath and keyPath to be set')
    }
    const httpsAgent = new https.Agent({
      rejectUnauthorized: true,
      cert: fs.readFileSync(certPath),
      key: fs.readFileSync(keyPath),
      passphrase
    })
    this._axios = axios.create({ baseURL, httpsAgent })
  }

  /**
  * Sends result to the CWA-backend and returns the hash and Corona Warn App URL of the test upon success.
  *
  * @param {Object} testData
  * @param {string} testData.fn  Vorname, UTF-8, maximale Länge 80 Zeichen
  * @param {string} testData.ln  Nachname, UTF-8, maximale Länge 80 Zeichen
  * @param {string} testData.dob Geburtsdatum im Format YYYY-MM-DD mit fester Länge von 10 Zeichen (Beispiel: 2000-01-01)
  * @param {number} testData.timestamp Test-Datum/Uhrzeit im Unix Epoch Timestamp Format (Sekunden)
  * @param {number} testData.result Testergebnis: Wertebereich 6 bis 8
  * @param {number} testData.[sc] Zeitpunkt der Testauswertung in unix epoch format UTC (Sekunden)
  * @return {Object} the generated json and the CWA URL
  */
  async sendTestResult ({ fn, ln, dob, timestamp, result, sc }) {
    const testid = uuidv4()
    const salt = uuidv4().replace(/-/g, '').toUpperCase()
    const hash = createHash('sha256')
      .update(`${dob}#${fn}#${ln}#${timestamp}#${testid}#${salt}`)
      .digest('hex')
    const { status, error } = await this._axios.post('/api/v1/quicktest/results', {
      testResults: [
        {
          id: hash,
          result, // 6 negativ, 7 positiv, 8 ungültig
          sc
        }
      ]
    })
    if (status === 204) {
      console.info('Test result was successfuly sent.')
      const json = {
        fn,
        ln,
        dob,
        timestamp,
        testid,
        salt,
        hash
      }
      return {
        ...json,
        cwaURL: this.getCwaURL(json)
      }
    }
    throw new Error(error)
  }

  /**
  * Builds the CWA URL from the test json
  *
  * @param {Object} The json object sent to the CWA-Backend with tha hash included
  * @return {string} The CWA url that can be used to open the test result in the app.
  */
  getCwaURL (json) {
    const base64Str = Buffer.from(JSON.stringify(json)).toString('base64')
    return `https://s.coronawarn.app?v=1#${base64Str}`
  }
}

module.exports = CwaAdapter
