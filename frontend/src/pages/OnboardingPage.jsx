import { useRef, useState } from 'react'
import { Link } from 'react-router-dom'

const onboardingDocumentTypes = '.pdf,.doc,.docx,.jpg,.jpeg,.png'
const maxFileSize = 10 * 1024 * 1024
const initialForm = {
  firstName: '',
  lastName: '',
  preferredName: '',
  dateOfBirth: '',
  gender: '',
  email: '',
  phone: '',
  address: '',
  city: '',
  postalCode: '',
  emergencyName: '',
  emergencyRelationship: '',
  emergencyPhone: '',
  startDate: '',
  employmentType: '',
  department: '',
  jobTitle: '',
  additionalInformation: '',
  declaration: false,
}

const requiredFields = {
  firstName: 'First name is required.',
  lastName: 'Last name is required.',
  email: 'Email address is required.',
  phone: 'Phone number is required.',
  emergencyName: 'Emergency contact name is required.',
  emergencyRelationship: 'Emergency contact relationship is required.',
  emergencyPhone: 'Emergency contact phone is required.',
  startDate: 'Start date is required.',
  employmentType: 'Employment type is required.',
  declaration: 'Please confirm that the information provided is accurate.',
}

function readStoredUser() {
  try {
    return JSON.parse(localStorage.getItem('hrms_user') || 'null')
  } catch {
    return null
  }
}

function getExtension(fileName) {
  return fileName.split('.').pop()?.toLowerCase() || ''
}

function formatFileSize(bytes) {
  if (bytes < 1024 * 1024) return `${Math.max(1, Math.round(bytes / 1024))} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

function OnboardingPage() {
  const user = readStoredUser()
  const role = user?.role_name || 'Employee'
  const isEmployee = role === 'Employee'
  const [formData, setFormData] = useState(initialForm)
  const [errors, setErrors] = useState({})
  const [touched, setTouched] = useState({})
  const [files, setFiles] = useState({ identification: null, certificate: null })
  const [fileError, setFileError] = useState('')
  const [clearMessage, setClearMessage] = useState('')
  const fileInputRefs = useRef({})
  const workspaceState = 'unavailable'

  const validate = (data = formData) => {
    const nextErrors = {}

    Object.entries(requiredFields).forEach(([field, message]) => {
      if (field === 'declaration' ? !data[field] : !String(data[field]).trim()) {
        nextErrors[field] = message
      }
    })

    if (data.email && !/^\S+@\S+\.\S+$/.test(data.email)) {
      nextErrors.email = 'Enter a valid email address.'
    }

    setErrors(nextErrors)
    setTouched(Object.keys(requiredFields).reduce((result, field) => ({ ...result, [field]: true }), {}))
    return nextErrors
  }

  const handleChange = (event) => {
    const { name, type, value, checked } = event.target
    setFormData((current) => ({ ...current, [name]: type === 'checkbox' ? checked : value }))
    setClearMessage('')
  }

  const handleBlur = (event) => {
    const { name } = event.target
    setTouched((current) => ({ ...current, [name]: true }))
    validate({ ...formData, [name]: event.target.type === 'checkbox' ? event.target.checked : event.target.value })
  }

  const handleFileChange = (event, documentType) => {
    const file = event.target.files?.[0]
    setFileError('')

    if (!file) return

    if (!['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'].includes(getExtension(file.name))) {
      setFileError('Unsupported document type. Choose a PDF, DOC, DOCX, JPG, JPEG, or PNG file.')
      event.target.value = ''
      return
    }

    if (file.size > maxFileSize) {
      setFileError('This document is larger than 10 MB. Choose a smaller file.')
      event.target.value = ''
      return
    }

    setFiles((current) => ({ ...current, [documentType]: file }))
  }

  const clearFile = (documentType) => {
    setFiles((current) => ({ ...current, [documentType]: null }))
    if (fileInputRefs.current[documentType]) fileInputRefs.current[documentType].value = ''
  }

  const handleClear = () => {
    setFormData(initialForm)
    setErrors({})
    setTouched({})
    setFiles({ identification: null, certificate: null })
    setFileError('')
    setClearMessage('Form cleared locally. Nothing was saved to the backend.')
  }

  const handleReview = (event) => {
    event.preventDefault()
    const nextErrors = validate()
    setClearMessage(Object.keys(nextErrors).length ? '' : 'All required fields are complete locally. Submission still requires the onboarding API.')
  }

  const fieldError = (field) => touched[field] ? errors[field] : ''
  const inputClass = (field) => `field-input ${fieldError(field) ? 'border-red-400' : ''}`

  const roleDescription = isEmployee
    ? 'Complete your own onboarding information. Your entries remain in this browser until an onboarding service is connected.'
    : `${role} access is ready for future onboarding review workflows. This screen does not display or create employee submissions yet.`

  const renderError = (field) => fieldError(field) && <p className="mt-1 text-xs text-red-600">{fieldError(field)}</p>

  return (
    <main className="min-h-screen bg-slate-100 p-4 sm:p-6 lg:p-8">
      <div className="mx-auto max-w-7xl">
        <header className="panel-surface mb-6 p-4 sm:p-6">
          <div className="flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
            <div>
              <p className="text-xs font-semibold uppercase tracking-[0.2em] text-sky-600">HRMS / Employee lifecycle</p>
              <h1 className="mt-2 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Onboarding Forms</h1>
              <p className="mt-2 max-w-2xl text-sm text-slate-600">Complete the information HR needs to prepare your employee record.</p>
            </div>
            <div className="flex flex-wrap items-center gap-3">
              <span className="rounded-full bg-sky-100 px-3 py-1 text-xs font-semibold text-sky-700">{role}</span>
              <Link to="/dashboard" className="action-button-secondary">Back to Dashboard</Link>
            </div>
          </div>
        </header>

        <section className="mb-6 grid gap-4 md:grid-cols-3">
          <div className="panel-surface p-5">
            <p className="section-label">Form status</p>
            <p className="mt-2 text-lg font-bold text-amber-600">Draft / not submitted</p>
            <p className="mt-1 text-sm text-slate-500">No draft has been loaded from the backend.</p>
          </div>
          <div className="panel-surface p-5">
            <p className="section-label">Connection</p>
            <p className="mt-2 text-lg font-bold text-amber-600">Backend unavailable</p>
            <p className="mt-1 text-sm text-slate-500">Onboarding API is not exposed yet.</p>
          </div>
          <div className="panel-surface p-5">
            <p className="section-label">Access scope</p>
            <p className="mt-2 text-lg font-bold text-emerald-600">{isEmployee ? 'My onboarding' : 'Review ready'}</p>
            <p className="mt-1 text-sm text-slate-500">Based on your current role.</p>
          </div>
        </section>

        <section className="panel-surface mb-6 p-5 sm:p-6">
          <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
              <p className="section-label">Onboarding workspace</p>
              <h2 className="mt-2 text-lg font-bold text-slate-900">{isEmployee ? 'Your onboarding information' : 'Onboarding review workspace'}</h2>
              <p className="mt-2 max-w-3xl text-sm leading-6 text-slate-600">{roleDescription}</p>
            </div>
            <span className="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700">Not connected</span>
          </div>

          {workspaceState === 'loading' && <div className="mt-5 rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-700" role="status">Loading onboarding information...</div>}
          {workspaceState === 'error' && <div className="mt-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700" role="alert">The onboarding service could not be reached. Try again when the backend is available.</div>}
          {workspaceState === 'unavailable' && <div className="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-800" role="status">This form is available for local completion, but loading, saving, and submitting onboarding information require a backend API.</div>}
        </section>

        <form onSubmit={handleReview} noValidate>
          <section className="panel-surface mb-6 p-5 sm:p-6">
            <div className="mb-5">
              <p className="section-label">Section 01</p>
              <h2 className="mt-2 text-lg font-bold text-slate-900">Personal Information</h2>
              <p className="mt-1 text-sm text-slate-600">Use the name and details that should appear on your employee record.</p>
            </div>
            <div className="grid gap-4 md:grid-cols-2">
              <label><span className="mb-2 block text-sm font-medium text-slate-700">First name <span className="text-red-500">*</span></span><input name="firstName" value={formData.firstName} onChange={handleChange} onBlur={handleBlur} className={inputClass('firstName')} autoComplete="given-name" />{renderError('firstName')}</label>
              <label><span className="mb-2 block text-sm font-medium text-slate-700">Last name <span className="text-red-500">*</span></span><input name="lastName" value={formData.lastName} onChange={handleChange} onBlur={handleBlur} className={inputClass('lastName')} autoComplete="family-name" />{renderError('lastName')}</label>
              <label><span className="mb-2 block text-sm font-medium text-slate-700">Preferred name</span><input name="preferredName" value={formData.preferredName} onChange={handleChange} className="field-input" autoComplete="nickname" /></label>
              <label><span className="mb-2 block text-sm font-medium text-slate-700">Date of birth</span><input name="dateOfBirth" type="date" value={formData.dateOfBirth} onChange={handleChange} className="field-input" /></label>
              <label><span className="mb-2 block text-sm font-medium text-slate-700">Gender</span><select name="gender" value={formData.gender} onChange={handleChange} className="field-input"><option value="">Select gender</option><option>Female</option><option>Male</option><option>Non-binary</option><option>Prefer not to say</option></select></label>
            </div>
          </section>

          <section className="panel-surface mb-6 p-5 sm:p-6">
            <div className="mb-5"><p className="section-label">Section 02</p><h2 className="mt-2 text-lg font-bold text-slate-900">Contact Information</h2></div>
            <div className="grid gap-4 md:grid-cols-2">
              <label><span className="mb-2 block text-sm font-medium text-slate-700">Email address <span className="text-red-500">*</span></span><input name="email" type="email" value={formData.email} onChange={handleChange} onBlur={handleBlur} className={inputClass('email')} autoComplete="email" />{renderError('email')}</label>
              <label><span className="mb-2 block text-sm font-medium text-slate-700">Phone number <span className="text-red-500">*</span></span><input name="phone" type="tel" value={formData.phone} onChange={handleChange} onBlur={handleBlur} className={inputClass('phone')} autoComplete="tel" />{renderError('phone')}</label>
              <label className="md:col-span-2"><span className="mb-2 block text-sm font-medium text-slate-700">Address</span><textarea name="address" value={formData.address} onChange={handleChange} className="field-input min-h-24" autoComplete="street-address" /></label>
              <label><span className="mb-2 block text-sm font-medium text-slate-700">City</span><input name="city" value={formData.city} onChange={handleChange} className="field-input" autoComplete="address-level2" /></label>
              <label><span className="mb-2 block text-sm font-medium text-slate-700">Postal code</span><input name="postalCode" value={formData.postalCode} onChange={handleChange} className="field-input" autoComplete="postal-code" /></label>
            </div>
          </section>

          <section className="panel-surface mb-6 p-5 sm:p-6">
            <div className="mb-5"><p className="section-label">Section 03</p><h2 className="mt-2 text-lg font-bold text-slate-900">Emergency Contact</h2><p className="mt-1 text-sm text-slate-600">Provide someone HR can contact in an emergency.</p></div>
            <div className="grid gap-4 md:grid-cols-3">
              <label><span className="mb-2 block text-sm font-medium text-slate-700">Full name <span className="text-red-500">*</span></span><input name="emergencyName" value={formData.emergencyName} onChange={handleChange} onBlur={handleBlur} className={inputClass('emergencyName')} />{renderError('emergencyName')}</label>
              <label><span className="mb-2 block text-sm font-medium text-slate-700">Relationship <span className="text-red-500">*</span></span><select name="emergencyRelationship" value={formData.emergencyRelationship} onChange={handleChange} onBlur={handleBlur} className={inputClass('emergencyRelationship')}><option value="">Select relationship</option><option>Parent</option><option>Partner</option><option>Sibling</option><option>Friend</option><option>Other</option></select>{renderError('emergencyRelationship')}</label>
              <label><span className="mb-2 block text-sm font-medium text-slate-700">Phone number <span className="text-red-500">*</span></span><input name="emergencyPhone" type="tel" value={formData.emergencyPhone} onChange={handleChange} onBlur={handleBlur} className={inputClass('emergencyPhone')} />{renderError('emergencyPhone')}</label>
            </div>
          </section>

          <section className="panel-surface mb-6 p-5 sm:p-6">
            <div className="mb-5"><p className="section-label">Section 04</p><h2 className="mt-2 text-lg font-bold text-slate-900">Employment Information</h2></div>
            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
              <label><span className="mb-2 block text-sm font-medium text-slate-700">Start date <span className="text-red-500">*</span></span><input name="startDate" type="date" value={formData.startDate} onChange={handleChange} onBlur={handleBlur} className={inputClass('startDate')} />{renderError('startDate')}</label>
              <label><span className="mb-2 block text-sm font-medium text-slate-700">Employment type <span className="text-red-500">*</span></span><select name="employmentType" value={formData.employmentType} onChange={handleChange} onBlur={handleBlur} className={inputClass('employmentType')}><option value="">Select type</option><option>Full-time</option><option>Part-time</option><option>Contract</option><option>Internship</option></select>{renderError('employmentType')}</label>
              <label><span className="mb-2 block text-sm font-medium text-slate-700">Department</span><input name="department" value={formData.department} onChange={handleChange} className="field-input" /></label>
              <label><span className="mb-2 block text-sm font-medium text-slate-700">Job title</span><input name="jobTitle" value={formData.jobTitle} onChange={handleChange} className="field-input" /></label>
            </div>
          </section>

          <section className="panel-surface mb-6 p-5 sm:p-6">
            <div className="mb-5"><p className="section-label">Section 05</p><h2 className="mt-2 text-lg font-bold text-slate-900">Required Documents</h2><p className="mt-1 text-sm text-slate-600">Files are selected locally only. They will not be uploaded without the onboarding API.</p></div>
            <div className="grid gap-4 md:grid-cols-2">
              {[['identification', 'Identification document'], ['certificate', 'Certificate or qualification']].map(([documentType, label]) => (
                <div key={documentType} className="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4">
                  <p className="text-sm font-semibold text-slate-800">{label}</p>
                  <label htmlFor={`onboarding-${documentType}`} className="mt-3 flex cursor-pointer items-center justify-center rounded-lg border border-sky-300 bg-white px-4 py-3 text-sm font-semibold text-sky-700 transition hover:bg-sky-50">Choose file<input ref={(element) => { fileInputRefs.current[documentType] = element }} id={`onboarding-${documentType}`} type="file" accept={onboardingDocumentTypes} onChange={(event) => handleFileChange(event, documentType)} className="sr-only" /></label>
                  {files[documentType] && <div className="mt-3 flex items-center justify-between gap-3 rounded-lg bg-white p-3 text-xs ring-1 ring-slate-200"><span className="min-w-0 truncate text-slate-700">{files[documentType].name} ({formatFileSize(files[documentType].size)})</span><button type="button" onClick={() => clearFile(documentType)} className="font-semibold text-slate-600 hover:text-slate-900">Remove</button></div>}
                </div>
              ))}
            </div>
            {fileError && <p className="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">{fileError}</p>}
          </section>

          <section className="panel-surface mb-6 p-5 sm:p-6">
            <div className="mb-5"><p className="section-label">Section 06</p><h2 className="mt-2 text-lg font-bold text-slate-900">Additional Information</h2></div>
            <label><span className="mb-2 block text-sm font-medium text-slate-700">Anything else HR should know?</span><textarea name="additionalInformation" value={formData.additionalInformation} onChange={handleChange} className="field-input min-h-28" placeholder="Add relevant information or questions for HR" /></label>
            <label className={`mt-5 flex items-start gap-3 rounded-xl border p-4 ${fieldError('declaration') ? 'border-red-300 bg-red-50' : 'border-slate-200 bg-slate-50'}`}><input name="declaration" type="checkbox" checked={formData.declaration} onChange={handleChange} onBlur={handleBlur} className="mt-1 h-4 w-4 accent-sky-600" /><span className="text-sm text-slate-700">I confirm that the information provided is accurate to the best of my knowledge. <span className="text-red-500">*</span>{renderError('declaration')}</span></label>
          </section>

          {clearMessage && <div className="mb-6 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-800" role="status">{clearMessage}</div>}

          <section className="panel-surface flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
            <div><p className="text-sm font-semibold text-slate-900">Backend actions are unavailable</p><p className="mt-1 text-sm text-slate-600">Review required fields locally, then connect the onboarding API before saving or submitting.</p></div>
            <div className="flex flex-wrap gap-3">
              <button type="button" onClick={handleClear} className="action-button-secondary">Clear form</button>
              <button type="submit" className="action-button-secondary">Review required fields</button>
              <button type="button" disabled className="action-button-secondary" title="Saving requires the onboarding API">Save draft</button>
              <button type="button" disabled className="action-button-primary" title="Submitting requires the onboarding API">Submit onboarding</button>
            </div>
          </section>
        </form>
      </div>
    </main>
  )
}

export default OnboardingPage
